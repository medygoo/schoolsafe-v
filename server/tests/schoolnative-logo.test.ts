import { mkdtempSync, mkdirSync, readdirSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import type { BusinessPool } from "../src/db/pool.js";
import { createSchoolNativeService } from "../src/schoolnative/service.js";
import type { AuthNativeService, AuthSessionInfo } from "../src/authnative/service.js";
import { lot4AuthStubs } from "./helpers/lot4-auth-stubs.js";

const SCHOOL_A = "33333333-0000-4000-8000-000000000001";
const SCHOOL_B = "99999999-0000-4000-8000-000000000099";
const PNG = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0x00, 0x00, 0x00, 0x0d]);
const OLD_FILE = "11111111-1111-4111-8111-111111111111.png";

type Mode = "ok" | "denied" | "write-denied";

function profile(logoPath: string | null) {
  return {
    identity: { name: "École A" },
    brand: { logo_path: logoPath },
    contact: {
      address: "1 Avenue Ancienne",
      city: "Kinshasa",
      province: "Kinshasa",
      country: "République démocratique du Congo",
      phone: "+243810000001",
      email: "ancienne@ecole-a.example",
      website_url: "https://ancienne.example",
    },
  };
}

function fakePool(mode: Mode, seen: { logoPath: string | null; params: unknown[][] }): BusinessPool {
  const client = {
    async query(sql: string, params?: unknown[]) {
      seen.params.push(params ?? []);
      if (sql.includes("api.school_official_profile_update")) {
        if (mode !== "ok") throw new Error("Access denied: school.manage");
        const payload = JSON.parse(String(params?.[0] ?? "{}")) as { logo_path?: string };
        seen.logoPath = payload.logo_path ?? null;
        return { rows: [{ school_official_profile_update: profile(seen.logoPath) }] };
      }
      if (sql.includes("api.school_official_profile_read")) {
        if (mode === "denied") throw new Error("Access denied: school.manage");
        return { rows: [{ school_official_profile_read: profile(seen.logoPath) }] };
      }
      return { rows: [] };
    },
    release() {},
  };
  return { connect: async () => client } as unknown as BusinessPool;
}

function fakeAuth(schoolId = SCHOOL_A): AuthNativeService {
  const record: AuthSessionInfo = {
    sessionId: "44444444-0000-4000-8000-000000000001",
    identityId: "77777777-0000-4000-8000-000000000001",
    userId: "55555555-0000-4000-8000-000000000001",
    profileId: "66666666-0000-4000-8000-000000000001",
    schoolId,
    mustChange: false,
    expiresAt: new Date(Date.now() + 3600_000).toISOString(),
  };
  return {
    async loginWithPassword() { throw new Error("not used"); },
    async resolveSession(token: string) { return token === "token-valide" ? record : null; },
    async touchSession() { return null; },
    async logout() { return true; },
    async listProfiles() { return []; },
    async forgotPassword() {},
    async resetPassword() { return false; },
    async switchProfile() { return { ok: false as const }; },
    ...lot4AuthStubs,
  };
}

function multipart(bytes: Buffer, type: string, extra?: { name: string; value: string }) {
  const boundary = "----logo-boundary";
  const chunks = [
    Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="logo"; filename="logo.bin"\r\nContent-Type: ${type}\r\n\r\n`),
    bytes,
    Buffer.from("\r\n"),
  ];
  if (extra) {
    chunks.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="${extra.name}"\r\n\r\n${extra.value}\r\n`));
  }
  chunks.push(Buffer.from(`--${boundary}--\r\n`));
  return { boundary, body: Buffer.concat(chunks) };
}

function makeApp(logoDir: string, mode: Mode = "ok", schoolId = SCHOOL_A) {
  const seen = { logoPath: null as string | null, params: [] as unknown[][] };
  const auth = fakeAuth(schoolId);
  const app = buildApp({
    authNative: { service: auth, cookieSecure: false },
    schoolNative: { authService: auth, service: createSchoolNativeService(fakePool(mode, seen)), logoDir },
  });
  return { app, seen };
}

describe("téléversement natif du logo", () => {
  it("401 sans session et 403 sans school.manage", async () => {
    const logoDir = mkdtempSync(path.join(tmpdir(), "schoolsafe-logo-"));
    const open = makeApp(logoDir);
    const denied = makeApp(logoDir, "denied");
    const body = multipart(PNG, "image/png");
    try {
      const anonymous = await open.app.inject({
        method: "POST",
        url: "/native/school/logo",
        headers: { "content-type": `multipart/form-data; boundary=${body.boundary}` },
        payload: body.body,
      });
      const operator = await denied.app.inject({
        method: "POST",
        url: `/native/school/logo?school_id=${SCHOOL_B}`,
        headers: { cookie: "schoolsafe_session=token-valide", "content-type": `multipart/form-data; boundary=${body.boundary}` },
        payload: body.body,
      });
      expect(anonymous.statusCode).toBe(401);
      expect(operator.statusCode).toBe(403);
      expect(readdirSync(logoDir)).toEqual([]);
    } finally {
      await open.app.close();
      await denied.app.close();
      rmSync(logoDir, { recursive: true, force: true });
    }
  });

  it("refuse un contenu qui n'est pas une image", async () => {
    const logoDir = mkdtempSync(path.join(tmpdir(), "schoolsafe-logo-"));
    const { app } = makeApp(logoDir);
    const body = multipart(Buffer.from("<svg xmlns='http://www.w3.org/2000/svg'></svg>"), "image/png");
    try {
      const response = await app.inject({
        method: "POST",
        url: "/native/school/logo",
        headers: { cookie: "schoolsafe_session=token-valide", "content-type": `multipart/form-data; boundary=${body.boundary}` },
        payload: body.body,
      });
      expect(response.statusCode).toBe(400);
      expect(response.json()).toMatchObject({ code: "FILE_INVALID" });
      expect(readdirSync(logoDir)).toEqual([]);
    } finally {
      await app.close();
      rmSync(logoDir, { recursive: true, force: true });
    }
  });

  it("enregistre le logo de l'école de session et le relit sans exposer une autre école", async () => {
    const logoDir = mkdtempSync(path.join(tmpdir(), "schoolsafe-logo-"));
    mkdirSync(path.join(logoDir, SCHOOL_A));
    writeFileSync(path.join(logoDir, SCHOOL_A, OLD_FILE), PNG);
    const first = makeApp(logoDir);
    const foreign = makeApp(logoDir, "ok", SCHOOL_B);
    const body = multipart(PNG, "image/png", { name: "school_id", value: SCHOOL_B });
    try {
      const uploaded = await first.app.inject({
        method: "POST",
        url: `/native/school/logo?school_id=${SCHOOL_B}`,
        headers: { cookie: "schoolsafe_session=token-valide", "content-type": `multipart/form-data; boundary=${body.boundary}` },
        payload: body.body,
      });
      expect(uploaded.statusCode).toBe(200);
      const logoPath = uploaded.json().logo_path as string;
      expect(logoPath).toMatch(/^\/native\/school\/logo-file\/[0-9a-f-]{36}\.png$/);
      expect(JSON.stringify(first.seen.params)).not.toContain(SCHOOL_B);
      expect(first.seen.logoPath).toBe(logoPath);
      const filename = logoPath.split("/").pop()!;
      const read = await first.app.inject({
        method: "GET",
        url: `${logoPath}?school_id=${SCHOOL_B}`,
        headers: { cookie: "schoolsafe_session=token-valide" },
      });
      const hidden = await foreign.app.inject({
        method: "GET",
        url: logoPath,
        headers: { cookie: "schoolsafe_session=token-valide" },
      });
      expect(read.statusCode).toBe(200);
      expect(read.rawPayload.subarray(0, 8)).toEqual(PNG.subarray(0, 8));
      expect(hidden.statusCode).toBe(404);
      expect(readdirSync(path.join(logoDir, SCHOOL_A)).sort()).toEqual([OLD_FILE, filename].sort());
      expect(readdirSync(logoDir).includes(SCHOOL_B)).toBe(false);
    } finally {
      await first.app.close();
      await foreign.app.close();
      rmSync(logoDir, { recursive: true, force: true });
    }
  });

  it("retire seulement le fichier nouveau si l'écriture officielle échoue", async () => {
    const logoDir = mkdtempSync(path.join(tmpdir(), "schoolsafe-logo-"));
    mkdirSync(path.join(logoDir, SCHOOL_A));
    writeFileSync(path.join(logoDir, SCHOOL_A, OLD_FILE), PNG);
    const { app } = makeApp(logoDir, "write-denied");
    const body = multipart(PNG, "image/png");
    try {
      const response = await app.inject({
        method: "POST",
        url: "/native/school/logo",
        headers: { cookie: "schoolsafe_session=token-valide", "content-type": `multipart/form-data; boundary=${body.boundary}` },
        payload: body.body,
      });
      expect(response.statusCode).toBe(403);
      expect(readdirSync(path.join(logoDir, SCHOOL_A))).toEqual([OLD_FILE]);
    } finally {
      await app.close();
      rmSync(logoDir, { recursive: true, force: true });
    }
  });
});

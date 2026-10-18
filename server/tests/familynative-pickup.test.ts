import { mkdtempSync, readdirSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import type { BusinessPool } from "../src/db/pool.js";
import { createFamilyNativeService } from "../src/familynative/service.js";
import type { AuthNativeService, AuthSessionInfo } from "../src/authnative/service.js";
import { lot4AuthStubs } from "./helpers/lot4-auth-stubs.js";

const SCHOOL_A = "33333333-0000-4000-8000-000000000001";
const SCHOOL_B = "99999999-0000-4000-8000-000000000099";
const PNG = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0x00]);

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

function pool(handler: (sql: string, params?: unknown[]) => unknown): { pool: BusinessPool; log: string[] } {
  const log: string[] = [];
  const client = {
    async query(sql: string, params?: unknown[]) {
      log.push(sql);
      const value = handler(sql, params);
      return { rows: [{ result: value }] };
    },
    release() {},
  };
  return { pool: { connect: async () => client } as unknown as BusinessPool, log };
}

function multipart(bytes: Buffer, type: string) {
  const boundary = "----portrait-boundary";
  const body = Buffer.concat([
    Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="portrait"; filename="portrait.bin"\r\nContent-Type: ${type}\r\n\r\n`),
    bytes,
    Buffer.from(`\r\n--${boundary}--\r\n`),
  ]);
  return { boundary, body };
}

describe("récupération et photographies", () => {
  it("conserve l'audit de refus après l'engagement de la transaction", async () => {
    const seen = pool(() => ({ status: "denied", code: "PHOTO_NOT_VALIDATED" }));
    const service = createFamilyNativeService(seen.pool);
    await expect(service.pickupConfirm({
      userId: "55555555-0000-4000-8000-000000000001",
      profileId: "66666666-0000-4000-8000-000000000001",
      schoolId: SCHOOL_A,
      requestId: "req-pickup",
    }, {
      student_id: "aaaaaaaa-0000-4000-8000-000000000001",
      guardian_id: "bbbbbbbb-0000-4000-8000-000000000001",
      request_key: "request-key-1",
    })).rejects.toMatchObject({ statusCode: 403, code: "ACCESS_DENIED" });
    expect(seen.log.some((sql) => sql === "COMMIT")).toBe(true);
    expect(seen.log.some((sql) => sql.startsWith("ROLLBACK"))).toBe(false);
    expect(seen.log.some((sql) => sql.includes("api.pickup_confirm"))).toBe(true);
  });

  it("refuse la réactivation directe lorsque le serveur refuse le droit", async () => {
    const seen = pool((sql) => {
      if (sql.includes("pickup_authorization_reactivate")) throw new Error("Access denied: pickup parent");
      return {};
    });
    const auth = fakeAuth();
    const app = buildApp({
      authNative: { service: auth, cookieSecure: false },
      familyNative: { authService: auth, service: createFamilyNativeService(seen.pool), portraitDir: mkdtempSync(path.join(tmpdir(), "schoolsafe-portrait-")) },
    });
    try {
      const anonymous = await app.inject({
        method: "POST",
        url: "/native/family/pickup-authorizations/cccccccc-0000-4000-8000-000000000001/parent-suspend",
        payload: { reason: "Absence" },
      });
      const reactivation = await app.inject({
        method: "POST",
        url: "/native/family/pickup-authorizations/cccccccc-0000-4000-8000-000000000001/reactivate",
        headers: { cookie: "schoolsafe_session=token-valide" },
      });
      expect(anonymous.statusCode).toBe(401);
      expect(reactivation.statusCode).toBe(403);
      expect(reactivation.json()).toMatchObject({ code: "ACCESS_DENIED" });
    } finally {
      await app.close();
    }
  });

  it("refuse un fichier invalide et isole la photographie d'une autre école", async () => {
    const portraitDir = mkdtempSync(path.join(tmpdir(), "schoolsafe-portrait-"));
    const seen = pool((sql, params) => {
      if (sql.includes("pickup_portrait_readable")) return false;
      if (sql.includes("scan_control_portrait_set")) {
        return { guardian_id: params?.[0], photo_path: params?.[1] };
      }
      return {};
    });
    const auth = fakeAuth();
    const foreign = fakeAuth(SCHOOL_B);
    const app = buildApp({
      authNative: { service: auth, cookieSecure: false },
      familyNative: { authService: auth, service: createFamilyNativeService(seen.pool), portraitDir },
    });
    const other = buildApp({
      authNative: { service: foreign, cookieSecure: false },
      familyNative: { authService: foreign, service: createFamilyNativeService(seen.pool), portraitDir },
    });
    const invalid = multipart(Buffer.from("not-an-image"), "image/png");
    const valid = multipart(PNG, "image/png");
    try {
      const rejected = await app.inject({
        method: "POST",
        url: "/native/family/guardians/bbbbbbbb-0000-4000-8000-000000000001/portrait",
        headers: { cookie: "schoolsafe_session=token-valide", "content-type": `multipart/form-data; boundary=${invalid.boundary}` },
        payload: invalid.body,
      });
      expect(rejected.statusCode).toBe(400);
      expect(rejected.json()).toMatchObject({ code: "FILE_INVALID" });
      expect(readdirSync(portraitDir)).toEqual([]);
      const stored = await app.inject({
        method: "POST",
        url: "/native/family/guardians/bbbbbbbb-0000-4000-8000-000000000001/portrait?school_id=" + SCHOOL_B,
        headers: { cookie: "schoolsafe_session=token-valide", "content-type": `multipart/form-data; boundary=${valid.boundary}` },
        payload: valid.body,
      });
      expect(stored.statusCode).toBe(200);
      const photoPath = stored.json().data.photo_path as string;
      expect(photoPath).toMatch(new RegExp(`^/uploads/authorized-persons/${SCHOOL_A}/[0-9a-f-]{36}\\.png$`));
      expect(photoPath).not.toContain(SCHOOL_B);
      const filename = photoPath.split("/").pop();
      const hidden = await app.inject({
        method: "GET",
        url: `/native/family/portrait-file/${filename}`,
        headers: { cookie: "schoolsafe_session=token-valide" },
      });
      const foreignRead = await other.inject({
        method: "GET",
        url: `/native/family/portrait-file/${filename}`,
        headers: { cookie: "schoolsafe_session=token-valide" },
      });
      expect(hidden.statusCode).toBe(404);
      expect(foreignRead.statusCode).toBe(404);
    } finally {
      await app.close();
      await other.close();
      rmSync(portraitDir, { recursive: true, force: true });
    }
  });

  it("accepte JPEG, PNG et WEBP et refuse un fichier trop volumineux", async () => {
    const portraitDir = mkdtempSync(path.join(tmpdir(), "schoolsafe-portrait-"));
    const seen = pool((sql, params) => {
      if (sql.includes("scan_control_portrait_set")) return { photo_path: params?.[1] };
      return {};
    });
    const auth = fakeAuth();
    const app = buildApp({
      authNative: { service: auth, cookieSecure: false },
      familyNative: { authService: auth, service: createFamilyNativeService(seen.pool), portraitDir },
    });
    const samples = [
      { bytes: Buffer.from([0xff, 0xd8, 0xff, 0x00]), type: "image/jpeg", ext: "jpg" },
      { bytes: PNG, type: "image/png", ext: "png" },
      { bytes: Buffer.concat([Buffer.from("RIFF"), Buffer.from([0, 0, 0, 0]), Buffer.from("WEBP")]), type: "image/webp", ext: "webp" },
    ];
    try {
      for (const sample of samples) {
        const body = multipart(sample.bytes, sample.type);
        const response = await app.inject({
          method: "POST",
          url: "/native/family/guardians/bbbbbbbb-0000-4000-8000-000000000001/portrait",
          headers: { cookie: "schoolsafe_session=token-valide", "content-type": `multipart/form-data; boundary=${body.boundary}` },
          payload: body.body,
        });
        expect(response.statusCode).toBe(200);
        expect(response.json().data.photo_path).toMatch(new RegExp(`\\.${sample.ext}$`));
      }
      const huge = multipart(Buffer.alloc(2 * 1024 * 1024 + 1, 0xff), "image/jpeg");
      const tooLarge = await app.inject({
        method: "POST",
        url: "/native/family/guardians/bbbbbbbb-0000-4000-8000-000000000001/portrait",
        headers: { cookie: "schoolsafe_session=token-valide", "content-type": `multipart/form-data; boundary=${huge.boundary}` },
        payload: huge.body,
      });
      expect(tooLarge.statusCode).toBe(400);
      expect(tooLarge.json().code).toBe("FILE_TOO_LARGE");
    } finally {
      await app.close();
      rmSync(portraitDir, { recursive: true, force: true });
    }
  });
});

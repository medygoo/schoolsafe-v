import { readFileSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import type { BusinessPool } from "../src/db/pool.js";
import { createSchoolNativeService } from "../src/schoolnative/service.js";
import type { AuthNativeService, AuthSessionInfo } from "../src/authnative/service.js";
import { lot4AuthStubs } from "./helpers/lot4-auth-stubs.js";

type QueryCall = { sql: string; params: unknown[] };

const SCHOOL_A = "33333333-0000-4000-8000-000000000001";
const SCHOOL_B = "99999999-0000-4000-8000-000000000099";

const BEFORE = {
  identity: { name: "École A" },
  brand: { logo_path: "/logos/a.png" },
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

const AFTER = {
  ...BEFORE,
  contact: {
    ...BEFORE.contact,
    address: "9 Avenue Nouvelle",
    phone: "+243810000009",
    email: "nouvelle@ecole-a.example",
    website_url: "https://nouvelle.example",
  },
};

function fakePool(log: QueryCall[], mode: "ok" | "denied"): BusinessPool {
  let current = BEFORE;
  const client = {
    async query(sql: string, params?: unknown[]) {
      log.push({ sql, params: params ?? [] });
      if (sql.includes("api.school_official_profile_update")) {
        if (mode === "denied") throw new Error("Access denied: school.manage");
        current = AFTER;
        return { rows: [{ school_official_profile_update: current }] };
      }
      if (sql.includes("api.school_official_profile_read")) {
        if (mode === "denied") throw new Error("Access denied: school.manage");
        return { rows: [{ school_official_profile_read: current }] };
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

function makeApp(log: QueryCall[], mode: "ok" | "denied" = "ok", schoolId = SCHOOL_A) {
  const auth = fakeAuth(schoolId);
  return buildApp({
    authNative: { service: auth, cookieSecure: false },
    schoolNative: { authService: auth, service: createSchoolNativeService(fakePool(log, mode)) },
  });
}

describe("fiche officielle native de l'école", () => {
  it("401 sans session", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log);
    const response = await app.inject({ method: "PUT", url: "/native/school/official-profile", payload: { address: "9 Avenue Nouvelle" } });
    expect(response.statusCode).toBe(401);
    expect(log).toHaveLength(0);
    await app.close();
  });

  it("403 pour un opérateur sans school.manage", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log, "denied");
    const response = await app.inject({
      method: "PUT",
      url: "/native/school/official-profile",
      headers: { cookie: "schoolsafe_session=token-valide" },
      payload: { address: "Adresse volée" },
    });
    expect(response.statusCode).toBe(403);
    expect(response.json()).toMatchObject({ code: "ACCESS_DENIED" });
    expect(JSON.stringify(response.json())).not.toContain("Adresse volée");
    await app.close();
  });

  it("un school_id injecté est refusé sans écriture", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log);
    const response = await app.inject({
      method: "PUT",
      url: `/native/school/official-profile?school_id=${SCHOOL_B}`,
      headers: { cookie: "schoolsafe_session=token-valide" },
      payload: { address: "Adresse volée", school_id: SCHOOL_B },
    });
    expect(response.statusCode).toBe(400);
    expect(log.some((entry) => entry.sql.includes("school_official_profile_update"))).toBe(false);
    await app.close();
  });

  it("une modification d'adresse est relue pour l'école de la session", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log);
    const headers = { cookie: "schoolsafe_session=token-valide" };
    const before = await app.inject({ method: "GET", url: "/native/school/official-profile", headers });
    const updated = await app.inject({
      method: "PUT",
      url: "/native/school/official-profile",
      headers,
      payload: {
        address: "9 Avenue Nouvelle",
        phone: "+243810000009",
        email: "nouvelle@ecole-a.example",
        website_url: "https://nouvelle.example",
      },
    });
    const again = await app.inject({ method: "GET", url: "/native/school/official-profile", headers });
    expect(before.json().data.contact.address).toBe("1 Avenue Ancienne");
    expect(updated.statusCode).toBe(200);
    expect(updated.json().data.contact.address).toBe("9 Avenue Nouvelle");
    expect(again.json().data.contact).toMatchObject({
      phone: "+243810000009",
      email: "nouvelle@ecole-a.example",
      website_url: "https://nouvelle.example",
    });
    const write = log.find((entry) => entry.sql.includes("api.school_official_profile_update"));
    expect(write?.params?.[0]).not.toContain(SCHOOL_B);
    expect(log[1]?.params.slice(0, 3)[2]).toBe(SCHOOL_A);
    expect(JSON.stringify(updated.json())).not.toContain("approval_code");
    await app.close();
  });

  it("l'école B conserve son propre contexte", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log, "ok", SCHOOL_B);
    const response = await app.inject({
      method: "GET",
      url: "/native/school/official-profile",
      headers: { cookie: "schoolsafe_session=token-valide" },
    });
    expect(response.statusCode).toBe(200);
    expect(log[1]?.params.slice(0, 3)[2]).toBe(SCHOOL_B);
    await app.close();
  });
});

describe("contrat SQL du dossier officiel", () => {
  const sql = readFileSync(
    path.resolve(import.meta.dirname, "../../database/cards/v2/03_card_year_and_school_profile.sql"),
    "utf8",
  );
  const updateSql = sql.slice(sql.indexOf("function api.school_official_profile_update"));

  it("réserve l'écriture à school.manage sur l'école de session", () => {
    expect(updateSql).toContain("iam.require_access('school.manage', null, null, null)");
    expect(updateSql).toContain("iam.current_school_id()");
    expect(updateSql).not.toContain("cards.request.print");
    expect(updateSql).toContain("audit.write_event");
    expect(updateSql).toContain("app.school_contacts");
  });
});

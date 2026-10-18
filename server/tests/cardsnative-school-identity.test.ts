import { readFileSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import type { BusinessPool } from "../src/db/pool.js";
import { createCardsNativeService } from "../src/cardsnative/service.js";
import type { AuthNativeService, AuthSessionInfo } from "../src/authnative/service.js";
import { lot4AuthStubs } from "./helpers/lot4-auth-stubs.js";

type QueryCall = { sql: string; params: unknown[] };

const SESSION_SCHOOL = "33333333-0000-4000-8000-000000000001";
const OTHER_SCHOOL = "99999999-0000-4000-8000-000000000099";

const IDENTITY = {
  identity: { name: "École Lumière", approval_code: "SECRET", legal_name: "Secret" },
  brand: { logo_path: "/logos/ecole.png", document_footer: "pied administratif", primary_color: "#000" },
  contact: {
    address: "12 Avenue de la Paix",
    city: "Kinshasa",
    province: "Kinshasa",
    country: "République démocratique du Congo",
    phone: "+243900000000",
    email: "direction@ecole.example",
    website_url: "https://ecole.example",
    website_mode: "privé",
    public_news: "interne",
    bank_account: "000-secret",
  },
  settings: { lockdown_active: true },
};

function fakePool(log: QueryCall[], mode: "ok" | "denied" | "missing"): BusinessPool {
  const client = {
    async query(sql: string, params?: unknown[]) {
      log.push({ sql, params: params ?? [] });
      if (sql.includes("api.school_card_identity")) {
        if (mode === "denied") throw new Error("Access denied: cards.request.print");
        if (mode === "missing") throw new Error("School not found");
        return { rows: [{ school_card_identity: IDENTITY }] };
      }
      return { rows: [] };
    },
    release() {},
  };
  return { connect: async () => client } as unknown as BusinessPool;
}

function fakeAuthService(): AuthNativeService {
  const record: AuthSessionInfo = {
    sessionId: "44444444-0000-4000-8000-000000000001",
    identityId: "77777777-0000-4000-8000-000000000001",
    userId: "55555555-0000-4000-8000-000000000001",
    profileId: "66666666-0000-4000-8000-000000000001",
    schoolId: SESSION_SCHOOL,
    mustChange: false,
    expiresAt: new Date(Date.now() + 3600_000).toISOString(),
  };
  return {
    async loginWithPassword() {
      throw new Error("not used");
    },
    async resolveSession(token: string) {
      return token === "token-valide" ? record : null;
    },
    async touchSession() {
      return null;
    },
    async logout() {
      return true;
    },
    async listProfiles() {
      return [];
    },
    async forgotPassword() {},
    async resetPassword() {
      return false;
    },
    async switchProfile() {
      return { ok: false as const };
    },
    ...lot4AuthStubs,
  };
}

function makeApp(log: QueryCall[], mode: "ok" | "denied" | "missing" = "ok") {
  const auth = fakeAuthService();
  return buildApp({
    authNative: { service: auth, cookieSecure: false },
    cardsNative: {
      authService: auth,
      service: createCardsNativeService(fakePool(log, mode)),
    },
  });
}

describe("GET /native/cards/school-identity", () => {
  it("401 sans session", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log);
    const response = await app.inject({ method: "GET", url: "/native/cards/school-identity" });
    expect(response.statusCode).toBe(401);
    expect(log).toHaveLength(0);
    await app.close();
  });

  it("401 session expirée", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log);
    const response = await app.inject({
      method: "GET",
      url: "/native/cards/school-identity",
      headers: { cookie: "schoolsafe_session=token-expire" },
    });
    expect(response.statusCode).toBe(401);
    expect(log).toHaveLength(0);
    await app.close();
  });

  it("403 quand cards.request.print est refusée", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log, "denied");
    const response = await app.inject({
      method: "GET",
      url: `/native/cards/school-identity?school_id=${OTHER_SCHOOL}`,
      headers: { cookie: "schoolsafe_session=token-valide" },
    });
    expect(response.statusCode).toBe(403);
    expect(response.json()).toMatchObject({ code: "ACCESS_DENIED" });
    expect(JSON.stringify(response.json())).not.toContain(OTHER_SCHOOL);
    expect(JSON.stringify(response.json())).not.toContain("École Lumière");
    await app.close();
  });

  it("200 pour l'école de la session, sans champs administratifs", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log);
    const response = await app.inject({
      method: "GET",
      url: `/native/cards/school-identity?school_id=${OTHER_SCHOOL}`,
      headers: { cookie: "schoolsafe_session=token-valide" },
    });
    expect(response.statusCode).toBe(200);
    expect(response.headers["cache-control"]).toBe("no-store");
    expect(response.json().data).toEqual({
      identity: { name: "École Lumière" },
      brand: { logo_path: "/logos/ecole.png" },
      contact: {
        address: "12 Avenue de la Paix",
        city: "Kinshasa",
        province: "Kinshasa",
        country: "République démocratique du Congo",
        phone: "+243900000000",
        email: "direction@ecole.example",
        website_url: "https://ecole.example",
      },
    });
    const body = JSON.stringify(response.json());
    expect(body).not.toContain("approval_code");
    expect(body).not.toContain("website_mode");
    expect(body).not.toContain("public_news");
    expect(body).not.toContain("bank_account");
    expect(body).not.toContain("lockdown_active");
    expect(body).not.toContain(OTHER_SCHOOL);
    const identityCall = log.find((call) => call.sql.includes("api.school_card_identity"));
    expect(identityCall?.params).toEqual([]);
    expect(log[1]?.params.slice(0, 3)).toEqual([
      "55555555-0000-4000-8000-000000000001",
      "66666666-0000-4000-8000-000000000001",
      SESSION_SCHOOL,
    ]);
    await app.close();
  });

  it("404 si l'école de la session n'a pas de fiche", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log, "missing");
    const response = await app.inject({
      method: "GET",
      url: "/native/cards/school-identity",
      headers: { cookie: "schoolsafe_session=token-valide" },
    });
    expect(response.statusCode).toBe(404);
    expect(response.json()).toMatchObject({ code: "SCHOOL_NOT_FOUND" });
    await app.close();
  });
});

describe("contrat SQL de l'identité carte", () => {
  const root = path.resolve(import.meta.dirname, "../..");
  const identitySql = readFileSync(path.join(root, "database/cards/v2/02_school_card_identity.sql"), "utf8");
  const roles = readFileSync(path.join(root, "database/access/v1/01_role_templates.sql"), "utf8");

  it("exige cards.request.print et l'école de session, sans school.manage", () => {
    expect(identitySql).toContain("iam.require_access('cards.request.print', null, null, null)");
    expect(identitySql).toContain("iam.current_school_id()");
    expect(identitySql).not.toContain("school.manage");
    expect(identitySql).not.toContain("p_school_id");
    const admin = roles.slice(roles.indexOf('"admin"'), roles.indexOf('"school_head"'));
    expect(admin).toContain('["cards.request.print","school"]');
    expect(admin).toContain('["school.manage","school"]');
  });
});

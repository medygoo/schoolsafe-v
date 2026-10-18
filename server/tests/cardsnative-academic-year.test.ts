import { readFileSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";
import { buildApp } from "../src/app.js";
import type { BusinessPool } from "../src/db/pool.js";
import { createCardsNativeService } from "../src/cardsnative/service.js";
import type { AuthNativeService, AuthSessionInfo } from "../src/authnative/service.js";
import { lot4AuthStubs } from "./helpers/lot4-auth-stubs.js";

type QueryCall = { sql: string; params: unknown[] };

const SCHOOL = "33333333-0000-4000-8000-000000000001";
const OTHER = "99999999-0000-4000-8000-000000000099";
const STUDENT = "11111111-0000-4000-8000-000000000001";
const CLASS_A = "22222222-0000-4000-8000-00000000000a";
const CLASS_B = "22222222-0000-4000-8000-00000000000b";
const YEAR_A = "44444444-0000-4000-8000-00000000000a";
const YEAR_B = "44444444-0000-4000-8000-00000000000b";

function fakePool(log: QueryCall[], mode: "ok" | "denied" | "missing"): BusinessPool {
  const client = {
    async query(sql: string, params?: unknown[]) {
      log.push({ sql, params: params ?? [] });
      if (!sql.includes("api.card_academic_year")) return { rows: [] };
      if (mode === "denied") throw new Error("Access denied: academic year");
      if (mode === "missing") return { rows: [{ card_academic_year: { academic_year_id: null, label: null } }] };
      const classId = params?.[1];
      if (classId === CLASS_B) {
        return { rows: [{ card_academic_year: { academic_year_id: YEAR_B, label: "2025-2026" } }] };
      }
      return { rows: [{ card_academic_year: { academic_year_id: YEAR_A, label: "2026-2027" } }] };
    },
    release() {},
  };
  return { connect: async () => client } as unknown as BusinessPool;
}

function fakeAuth(): AuthNativeService {
  const record: AuthSessionInfo = {
    sessionId: "44444444-0000-4000-8000-000000000001",
    identityId: "77777777-0000-4000-8000-000000000001",
    userId: "55555555-0000-4000-8000-000000000001",
    profileId: "66666666-0000-4000-8000-000000000001",
    schoolId: SCHOOL,
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

function makeApp(log: QueryCall[], mode: "ok" | "denied" | "missing" = "ok") {
  const auth = fakeAuth();
  return buildApp({
    authNative: { service: auth, cookieSecure: false },
    cardsNative: { authService: auth, service: createCardsNativeService(fakePool(log, mode)) },
  });
}

describe("GET /native/cards/students/:id/academic-year", () => {
  it("401 sans session", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log);
    const response = await app.inject({
      method: "GET",
      url: `/native/cards/students/${STUDENT}/academic-year?class_id=${CLASS_A}`,
    });
    expect(response.statusCode).toBe(401);
    expect(log).toHaveLength(0);
    await app.close();
  });

  it("classe A reçoit 2026-2027 et la classe B une autre année", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log);
    const headers = { cookie: "schoolsafe_session=token-valide" };
    const first = await app.inject({
      method: "GET",
      url: `/native/cards/students/${STUDENT}/academic-year?class_id=${CLASS_A}&school_id=${OTHER}`,
      headers,
    });
    const second = await app.inject({
      method: "GET",
      url: `/native/cards/students/${STUDENT}/academic-year?class_id=${CLASS_B}`,
      headers,
    });
    expect(first.statusCode).toBe(200);
    expect(first.json().data).toEqual({ academic_year_id: YEAR_A, label: "2026-2027" });
    expect(second.json().data.label).toBe("2025-2026");
    expect(JSON.stringify(first.json())).not.toContain(OTHER);
    const call = log.find((entry) => entry.sql.includes("api.card_academic_year"));
    expect(call?.params).toEqual([STUDENT, CLASS_A]);
    expect(log[1]?.params.slice(0, 3)[2]).toBe(SCHOOL);
    await app.close();
  });

  it("403 pour une année hors de l'école de session", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log, "denied");
    const response = await app.inject({
      method: "GET",
      url: `/native/cards/students/${STUDENT}/academic-year?class_id=${CLASS_A}`,
      headers: { cookie: "schoolsafe_session=token-valide" },
    });
    expect(response.statusCode).toBe(403);
    expect(response.json()).toMatchObject({ code: "ACCESS_DENIED" });
    await app.close();
  });

  it("année officielle absente : libellé vide, sans année inventée", async () => {
    const log: QueryCall[] = [];
    const app = makeApp(log, "missing");
    const response = await app.inject({
      method: "GET",
      url: `/native/cards/students/${STUDENT}/academic-year?class_id=${CLASS_A}`,
      headers: { cookie: "schoolsafe_session=token-valide" },
    });
    expect(response.statusCode).toBe(200);
    expect(response.json().data).toEqual({ academic_year_id: null, label: null });
    await app.close();
  });
});

describe("contrat SQL de l'année de carte", () => {
  const sql = readFileSync(
    path.resolve(import.meta.dirname, "../../database/cards/v2/03_card_year_and_school_profile.sql"),
    "utf8",
  );
  const yearSql = sql.slice(sql.indexOf("function api.card_academic_year"), sql.indexOf("function api.school_official_profile_read"));

  it("lie l'inscription, la classe et l'année de la même école", () => {
    expect(yearSql).toContain("iam.require_access('cards.request.print', null, p_student_id, p_class_id)");
    expect(yearSql).toContain("iam.current_school_id()");
    expect(yearSql).toContain("app.student_enrollments");
    expect(yearSql).toContain("e.status = 'active'");
    expect(yearSql).not.toContain("school.manage");
    expect(yearSql).not.toContain("current_date");
    expect(yearSql).not.toContain("p_school_id");
  });
});

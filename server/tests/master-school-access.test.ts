import { readFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it, vi } from "vitest";
import { buildApp } from "../src/app.js";
import { createAuthNativeService, openResolvedSupabaseSession, type AuthDatabase } from "../src/authnative/service.js";
import { hashPassword } from "../src/authnative/passwords.js";
import { classifyMasterPrincipal, lookupSupabaseSubject } from "../src/masteraccess/admin.js";
import { createMasterSchoolAccessReconciler, type MasterAccessTarget } from "../src/masteraccess/reconciler.js";
import { acceptReplacementSubject, assertRelinkRequest } from "../src/masteraccess/relink.js";
import { parseEnv } from "../src/config/env.js";
import { SchoolSafeError } from "../src/http/errors.js";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const read = (file: string) => readFileSync(path.join(root, file), "utf8");
const SCHOOL = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const SUBJECT = "11111111-1111-4111-8111-111111111111";
const NEW_SUBJECT = "22222222-2222-4222-8222-222222222222";

function rowsOf(handler: (sql: string, params: unknown[]) => unknown[] | Promise<unknown[]>): AuthDatabase {
  return {
    async query<T>(sql: string, params: unknown[] = []) {
      return { rows: (await handler(sql, params)) as T[] };
    },
  };
}

function target(status: MasterAccessTarget["access_status"]): MasterAccessTarget {
  return {
    school_id: SCHOOL,
    principal_user_id: "33333333-3333-4333-8333-333333333333",
    principal_profile_id: "44444444-4444-4444-8444-444444444444",
    supabase_subject: SUBJECT,
    access_status: status,
    last_verified_at: null,
  };
}

describe("master school access decisions", () => {
  it("keeps an active principal active and a future ban suspended", () => {
    expect(classifyMasterPrincipal({ kind: "found", bannedUntil: null })).toBe("active");
    expect(classifyMasterPrincipal({
      kind: "found",
      bannedUntil: "2099-01-01T00:00:00.000Z",
    })).toBe("suspended");
    expect(classifyMasterPrincipal({
      kind: "found",
      bannedUntil: "2000-01-01T00:00:00.000Z",
    })).toBe("active");
  });

  it("treats a real 404 as revoked and a network failure as unchanged", async () => {
    expect(classifyMasterPrincipal({ kind: "missing" })).toBe("revoked");
    expect(classifyMasterPrincipal({ kind: "unavailable" })).toBe("unchanged");
    const unavailable = await lookupSupabaseSubject({
      url: "https://supabase.example.test",
      serviceRoleKey: "server-only-service-role-key",
      subject: SUBJECT,
      fetchImpl: async () => { throw new Error("network down"); },
    });
    expect(unavailable).toEqual({ kind: "unavailable" });
    const missing = await lookupSupabaseSubject({
      url: "https://supabase.example.test",
      serviceRoleKey: "server-only-service-role-key",
      subject: SUBJECT,
      fetchImpl: async () => new Response("{}", { status: 404 }),
    });
    expect(missing).toEqual({ kind: "missing" });
    const banned = await lookupSupabaseSubject({
      url: "https://supabase.example.test",
      serviceRoleKey: "server-only-service-role-key",
      subject: SUBJECT,
      fetchImpl: async (_url, init) => {
        const headers = new Headers(init?.headers);
        expect(headers.get("authorization")).toBe("Bearer server-only-service-role-key");
        expect(String(_url)).toBe(`https://supabase.example.test/auth/v1/admin/users/${SUBJECT}`);
        return new Response(JSON.stringify({ banned_until: "2099-01-01T00:00:00.000Z" }), { status: 200 });
      },
    });
    expect(banned).toEqual({ kind: "found", bannedUntil: "2099-01-01T00:00:00.000Z" });
  });

  it("does not turn a network failure into revoked and does not reopen a revoked school", async () => {
    const calls: unknown[][] = [];
    const db = rowsOf((_sql, params) => {
      calls.push(params);
      return [target("active")];
    });
    const down = createMasterSchoolAccessReconciler({
      db,
      intervalMs: 30000,
      lookup: async () => ({ kind: "unavailable" }),
    });
    const first = await down.runOnce();
    expect(first.applied).toBe(0);
    expect(calls).toHaveLength(1);

    const revokedDb = rowsOf((sql, params) => {
      if (sql.includes("master_school_access_apply")) calls.push(params);
      return [target("revoked")];
    });
    calls.length = 0;
    const reconciler = createMasterSchoolAccessReconciler({
      db: revokedDb,
      intervalMs: 30000,
      lookup: async () => ({ kind: "found", bannedUntil: null }),
    });
    expect((await reconciler.runOnce()).applied).toBe(0);
    expect(calls).toHaveLength(0);
  });

  it("applies a simulated ban and a real deletion only for the stored subject", async () => {
    const applied: unknown[][] = [];
    const db = rowsOf((sql, params) => {
      if (sql.includes("master_school_access_apply")) {
        applied.push(params);
        return [];
      }
      return [target("active")];
    });
    const banned = createMasterSchoolAccessReconciler({
      db,
      intervalMs: 30000,
      lookup: async (subject) => {
        expect(subject).toBe(SUBJECT);
        return { kind: "found", bannedUntil: "2099-01-01T00:00:00.000Z" };
      },
    });
    expect((await banned.runOnce()).applied).toBe(1);
    expect(applied[0]).toEqual([SCHOOL, SUBJECT, "suspended"]);

    applied.length = 0;
    const deleted = createMasterSchoolAccessReconciler({
      db: rowsOf((sql, params) => {
        if (sql.includes("master_school_access_apply")) {
          applied.push(params);
          return [];
        }
        return [target("suspended")];
      }),
      intervalMs: 30000,
      lookup: async () => ({ kind: "missing" }),
    });
    expect((await deleted.runOnce()).applied).toBe(1);
    expect(applied[0]).toEqual([SCHOOL, SUBJECT, "revoked"]);
  });

  it("does not overlap a sync that is already running", async () => {
    let release: () => void = () => {};
    const gate = new Promise<void>((resolve) => { release = resolve; });
    let started = 0;
    const reconciler = createMasterSchoolAccessReconciler({
      db: rowsOf(async () => {
        started += 1;
        await gate;
        return [];
      }),
      intervalMs: 30000,
      lookup: async () => ({ kind: "unavailable" }),
    });
    const first = reconciler.runOnce();
    await vi.waitFor(() => expect(started).toBe(1));
    expect(await reconciler.runOnce()).toEqual({ skipped: true, applied: 0 });
    release();
    expect(await first).toEqual({ skipped: false, applied: 0 });
  });
});

describe("master school access sessions", () => {
  it("refuses a suspended school before creating a session and on an existing session", async () => {
    const passwordHash = await hashPassword("Joie2026!!");
    const calls: string[] = [];
    const db = rowsOf((sql) => {
      calls.push(sql);
      if (sql.includes("master_school_access_status")) return [{ master_school_access_status: "suspended" }];
      if (sql.includes("auth_is_locked")) return [{ auth_is_locked: false }];
      if (sql.includes("auth_resolve_identity")) return [{ identity_id: "i1", user_id: "u1", password_hash: passwordHash, status: "active", must_change: false }];
      if (sql.includes("auth_control_link")) return [];
      if (sql.includes("auth_record_attempt")) return [{ auth_record_attempt: true }];
      if (sql.includes("auth_list_profiles")) return [{ profile_id: "p1", school_id: SCHOOL, school_code: "A", school_name: "A", display_name: "Principal" }];
      if (sql.includes("auth_create_session")) return [{ session_id: "s1", expires_at: "2099-01-01T00:00:00.000Z" }];
      if (sql.includes("auth_resolve_session")) return [{ session_id: "s1", identity_id: "i1", user_id: "u1", profile_id: "p1", school_id: SCHOOL, must_change: false }];
      return [];
    });
    const service = createAuthNativeService({ db });
    await expect(service.loginWithPassword("joyce@ecole.cd", "Joie2026!!")).rejects.toMatchObject({ code: "SCHOOL_SUSPENDED", statusCode: 403 });
    expect(calls.some((sql) => sql.includes("auth_create_session"))).toBe(false);
    await expect(service.resolveSession("opaque-session-token")).rejects.toMatchObject({ code: "SCHOOL_SUSPENDED" });
  });

  it("allows a new login after reactivation without reviving the refused session", async () => {
    const passwordHash = await hashPassword("Joie2026!!");
    const db = rowsOf((sql) => {
      if (sql.includes("master_school_access_status")) return [{ master_school_access_status: "active" }];
      if (sql.includes("auth_is_locked")) return [{ auth_is_locked: false }];
      if (sql.includes("auth_resolve_identity")) return [{ identity_id: "i1", user_id: "u1", password_hash: passwordHash, status: "active", must_change: false }];
      if (sql.includes("auth_control_link")) return [];
      if (sql.includes("auth_record_attempt")) return [{ auth_record_attempt: true }];
      if (sql.includes("auth_list_profiles")) return [{ profile_id: "p1", school_id: SCHOOL, school_code: "A", school_name: "A", display_name: "Principal" }];
      if (sql.includes("auth_create_session")) return [{ session_id: "s-new", expires_at: "2099-01-01T00:00:00.000Z" }];
      if (sql.includes("auth_resolve_session")) return [];
      return [];
    });
    const service = createAuthNativeService({ db });
    const opened = await service.loginWithPassword("joyce@ecole.cd", "Joie2026!!");
    expect(opened.ok).toBe(true);
    expect(await service.resolveSession("old-revoked-token")).toBeNull();
  });

  it("blocks Supabase session opening and a non-native resolveSession route", async () => {
    const db = rowsOf((sql) => {
      if (sql.includes("master_school_access_status")) return [{ master_school_access_status: "revoked" }];
      if (sql.includes("auth_supabase_session_identity")) return [{ identity_id: "identity-1" }];
      if (sql.includes("auth_resolve_session")) return [{ session_id: "s1", identity_id: "i1", user_id: "u1", profile_id: "p1", school_id: SCHOOL, must_change: false }];
      if (sql.includes("auth_control_link")) return [];
      if (sql.includes("auth_create_session")) return [{ session_id: "s1", expires_at: "2099-01-01T00:00:00.000Z" }];
      return [];
    });
    await expect(openResolvedSupabaseSession(db, {
      userId: "u1",
      profileId: "p1",
      schoolId: SCHOOL,
    })).rejects.toMatchObject({ code: "SCHOOL_REVOKED" });
    const service = createAuthNativeService({ db });
    const app = buildApp({ authNative: { service, cookieSecure: false } });
    const response = await app.inject({
      method: "GET",
      url: "/auth/native/me",
      headers: { cookie: "schoolsafe_session=opaque-session-token" },
    });
    expect(response.statusCode).toBe(403);
    expect(response.json().code).toBe("SCHOOL_REVOKED");
    expect(response.body).not.toContain(SUBJECT);
    await app.close();
  });

  it("leaves a school with no master access row on the legacy path", async () => {
    const passwordHash = await hashPassword("Joie2026!!");
    const db = rowsOf((sql) => {
      if (sql.includes("master_school_access_status")) return [{ master_school_access_status: null }];
      if (sql.includes("auth_is_locked")) return [{ auth_is_locked: false }];
      if (sql.includes("auth_resolve_identity")) return [{ identity_id: "i1", user_id: "u1", password_hash: passwordHash, status: "active", must_change: false }];
      if (sql.includes("auth_control_link")) return [];
      if (sql.includes("auth_record_attempt")) return [{ auth_record_attempt: true }];
      if (sql.includes("auth_list_profiles")) return [{ profile_id: "p1", school_id: SCHOOL, school_code: "A", school_name: "A", display_name: "Principal" }];
      if (sql.includes("auth_create_session")) return [{ session_id: "s1", expires_at: "2099-01-01T00:00:00.000Z" }];
      return [];
    });
    const result = await createAuthNativeService({ db }).loginWithPassword("joyce@ecole.cd", "Joie2026!!");
    expect(result.ok).toBe(true);
  });
});

describe("master school access relink and browser boundary", () => {
  it("requires an explicit exact subject and rejects a banned replacement", () => {
    expect(() => assertRelinkRequest({
      schoolId: SCHOOL,
      oldSubject: SUBJECT,
      newSubject: NEW_SUBJECT,
      confirm: "no",
    })).toThrow("CONFIRMATION_REQUIRED");
    expect(() => assertRelinkRequest({
      schoolId: SCHOOL,
      oldSubject: SUBJECT,
      newSubject: SUBJECT,
      confirm: "RELINK",
    })).toThrow("EXACT_SUBJECT_REQUIRED");
    assertRelinkRequest({ schoolId: SCHOOL, oldSubject: SUBJECT, newSubject: NEW_SUBJECT, confirm: "RELINK" });
    expect(acceptReplacementSubject({ kind: "found", bannedUntil: null })).toBe(true);
    expect(acceptReplacementSubject({ kind: "found", bannedUntil: "2099-01-01T00:00:00.000Z" })).toBe(false);
    expect(acceptReplacementSubject({ kind: "missing" })).toBe(false);
    expect(acceptReplacementSubject({ kind: "unavailable" })).toBe(false);
  });

  it("keeps the maintenance command off the public routes and the service role out of the browser", () => {
    const cli = read("scripts/master-school-access-relink.mjs");
    expect(cli).toContain("RELINK");
    expect(cli).toContain("/auth/v1/admin/users/");
    expect(cli).not.toContain("app.post");
    expect(cli).not.toContain("app.get");
    const browser = ["app/app.js", "app/modules/authnative/auth-native.js", "app/index.html"]
      .map(read)
      .join("\n");
    expect(browser).not.toContain("service_role");
    expect(browser).not.toContain("SUPABASE_SERVICE_ROLE");
    expect(browser).toContain("L’accès à cet établissement est suspendu. Contactez PRODELI.");
    expect(browser).toContain("L’accès à cet établissement a été révoqué. Contactez PRODELI.");
    const notice = browser.slice(browser.indexOf("function schoolAccessNotice"), browser.indexOf("async function finishSupabaseExchange"));
    expect(notice).toContain("SCHOOL_SUSPENDED");
    expect(notice).toContain("SCHOOL_REVOKED");
    expect(notice).not.toContain("showScreen");
    const result = spawnSync(process.execPath, ["scripts/master-school-access-relink.mjs"], { cwd: root, encoding: "utf8" });
    expect(result.status).toBe(2);
    expect(result.stderr).toContain("CONFIRMATION_REQUIRED");
  });

  it("bounds the sync interval and does not expose the service role through parsed public config", () => {
    expect(parseEnv({ NODE_ENV: "test" }).MASTER_SCHOOL_ACCESS_SYNC_MS).toBe(30000);
    expect(() => parseEnv({ NODE_ENV: "test", MASTER_SCHOOL_ACCESS_SYNC_MS: "999" })).toThrow(/MASTER_SCHOOL_ACCESS_SYNC_MS/);
    const source = read("server/src/setup/service.ts") + read("server/src/setup/routes.ts");
    expect(source).not.toContain("SUPABASE_SERVICE_ROLE");
    expect(source).not.toContain("service_role");
  });
});

describe("master school access error type", () => {
  it("uses the public SchoolSafe error for a suspended school", () => {
    const error = new SchoolSafeError(403, "SCHOOL_SUSPENDED", "L’accès à cet établissement est suspendu. Contactez PRODELI.", false);
    expect(error.code).toBe("SCHOOL_SUSPENDED");
  });
});

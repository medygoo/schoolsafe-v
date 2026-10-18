import type { AuthDatabase } from "../authnative/service.js";
import { classifyMasterPrincipal, type AdminLookup, type MasterAccessDecision } from "./admin.js";

export type MasterAccessTarget = {
  school_id: string;
  principal_user_id: string;
  principal_profile_id: string;
  supabase_subject: string;
  access_status: "active" | "suspended" | "revoked";
  last_verified_at: string | null;
};

export type MasterSchoolAccessReconciler = {
  runOnce(): Promise<{ skipped: boolean; applied: number }>;
  start(): void;
  stop(): void;
};

export function createMasterSchoolAccessReconciler(options: {
  db: AuthDatabase;
  lookup: (subject: string) => Promise<AdminLookup>;
  intervalMs: number;
  concurrency?: number;
  now?: () => Date;
}): MasterSchoolAccessReconciler {
  let running = false;
  let timer: ReturnType<typeof setInterval> | undefined;
  const concurrency = Math.max(1, options.concurrency ?? 2);

  async function apply(target: MasterAccessTarget, next: MasterAccessDecision): Promise<boolean> {
    if (next === "unchanged") return false;
    if (target.access_status === "revoked" && next !== "revoked") return false;
    await options.db.query(
      "select api.master_school_access_apply($1, $2, $3)",
      [target.school_id, target.supabase_subject, next],
    );
    return true;
  }

  async function runOnce(): Promise<{ skipped: boolean; applied: number }> {
    if (running) return { skipped: true, applied: 0 };
    running = true;
    try {
      const targets = await options.db.query<MasterAccessTarget>(
        "select * from api.master_school_access_targets()",
        [],
      );
      let applied = 0;
      let index = 0;
      const now = options.now ?? (() => new Date());
      async function worker() {
        for (;;) {
          const current = index;
          index += 1;
          const target = targets.rows[current];
          if (!target) return;
          const decision = classifyMasterPrincipal(await options.lookup(target.supabase_subject), now());
          if (await apply(target, decision)) applied += 1;
        }
      }
      const workers = Math.min(concurrency, Math.max(targets.rows.length, 1));
      await Promise.all(Array.from({ length: targets.rows.length === 0 ? 0 : workers }, () => worker()));
      return { skipped: false, applied };
    } catch {
      console.error("master school access sync skipped");
      return { skipped: false, applied: 0 };
    } finally {
      running = false;
    }
  }

  return {
    runOnce,
    start() {
      if (timer) return;
      void runOnce();
      timer = setInterval(() => { void runOnce(); }, options.intervalMs);
      timer.unref?.();
    },
    stop() {
      if (!timer) return;
      clearInterval(timer);
      timer = undefined;
    },
  };
}

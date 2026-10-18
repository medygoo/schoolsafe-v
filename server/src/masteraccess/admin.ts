export type AdminLookup =
  | { kind: "found"; bannedUntil: string | null }
  | { kind: "missing" }
  | { kind: "unavailable" };

export type MasterAccessDecision = "active" | "suspended" | "revoked" | "unchanged";

export function bannedUntilIsActive(value: string | null | undefined, now = new Date()): boolean {
  if (!value || value === "none") return false;
  const until = Date.parse(value);
  return Number.isFinite(until) && until > now.getTime();
}

export function classifyMasterPrincipal(lookup: AdminLookup, now = new Date()): MasterAccessDecision {
  if (lookup.kind === "unavailable") return "unchanged";
  if (lookup.kind === "missing") return "revoked";
  if (bannedUntilIsActive(lookup.bannedUntil, now)) return "suspended";
  return "active";
}

export async function lookupSupabaseSubject(options: {
  url: string;
  serviceRoleKey: string;
  subject: string;
  fetchImpl?: typeof fetch;
  timeoutMs?: number;
}): Promise<AdminLookup> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), options.timeoutMs ?? 8000);
  try {
    const response = await (options.fetchImpl ?? fetch)(
      `${options.url.replace(/\/$/, "")}/auth/v1/admin/users/${encodeURIComponent(options.subject)}`,
      {
        method: "GET",
        headers: {
          apikey: options.serviceRoleKey,
          authorization: `Bearer ${options.serviceRoleKey}`,
          accept: "application/json",
        },
        signal: controller.signal,
      },
    );
    if (response.status === 404) return { kind: "missing" };
    if (!response.ok) return { kind: "unavailable" };
    const body = await response.json() as { banned_until?: string | null };
    return { kind: "found", bannedUntil: body.banned_until ?? null };
  } catch {
    return { kind: "unavailable" };
  } finally {
    clearTimeout(timer);
  }
}

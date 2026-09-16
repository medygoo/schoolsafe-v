import type { PoolClient } from "pg";
import type { BusinessPool } from "../db/pool.js";
import { withAuthorizedContext } from "../db/access.js";
import type { RequestContext } from "../db/context.js";
import { SchoolSafeError } from "../http/errors.js";

export type AccessPageInput = { query: string; limit: number; offset: number };
export type AccessProfile = { id: string; display_name: string; is_active: boolean; account_status: string };
export type AccessRole = { id: string; code: string; label: string; is_active: boolean };
export type AccessPage<T> = { schoolId: string; rows: T[]; total: number; limit: number; offset: number };
type Validity = { is_active: boolean; starts_at: string; ends_at: string | null };
export type AccessScope = Validity & { type: string; target: string | null };
export type AccessGrant = Validity & {
  id: string; role_id: string; permission: string; permission_active: boolean;
  effect: "allow" | "deny"; scopes: AccessScope[];
  conditions: { code: string; is_active: boolean }[];
};
export type AccessProfileDetail = {
  schoolId: string; profile: AccessProfile;
  roles: (AccessRole & { assignment: Validity })[];
  grants: AccessGrant[];
  exceptions: (Validity & {
    id: string; permission: string; permission_active: boolean;
    effect: "allow" | "deny"; condition_code: string | null; scopes: AccessScope[];
  })[];
};

export function createAccessNativeService(pool: BusinessPool) {
  async function read<T>(context: RequestContext, sql: string, params: unknown[]): Promise<T> {
    try {
      // No targetProfileId here: even consulting oneself requires school-level management.
      return await withAuthorizedContext(pool, context, "roles.manage", {}, async (client: PoolClient) => {
        const result = await client.query<{ data: T }>(sql, params);
        if (!result.rows.length) throw new Error("Missing access projection");
        return result.rows[0].data;
      });
    } catch (error) {
      // Includes a context/permission revoked between the first check and the RPC.
      if ((error as { code?: string }).code === "42501") {
        throw new SchoolSafeError(403, "PERMISSION_DENIED", "Accès refusé", false);
      }
      throw error;
    }
  }
  return {
    listProfiles(context: RequestContext, input: AccessPageInput) {
      return read<AccessPage<AccessProfile>>(context, "select api.access_profiles_list($1, $2, $3) as data", [input.query, input.limit, input.offset]);
    },
    listRoles(context: RequestContext, input: AccessPageInput) {
      return read<AccessPage<AccessRole>>(context, "select api.access_roles_list($1, $2, $3) as data", [input.query, input.limit, input.offset]);
    },
    readProfile(context: RequestContext, profileId: string) {
      return read<AccessProfileDetail | null>(context, "select api.access_profile_read($1) as data", [profileId]);
    },
  };
}
export type AccessNativeService = ReturnType<typeof createAccessNativeService>;

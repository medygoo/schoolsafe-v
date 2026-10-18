import type { PoolClient } from "pg";
import type { BusinessPool } from "../db/pool.js";
import { withRequestContext, type RequestContext } from "../db/context.js";
import { projectSchoolCardIdentity, type SchoolCardIdentity } from "../cardsnative/service.js";

export type SchoolOfficialProfileInput = {
  name?: string;
  logo_path?: string | null;
  address?: string | null;
  city?: string | null;
  province?: string | null;
  country?: string | null;
  phone?: string | null;
  email?: string | null;
  website_url?: string | null;
};

export function createSchoolNativeService(businessPool: BusinessPool) {
  return {
    async readOfficialProfile(context: RequestContext): Promise<SchoolCardIdentity> {
      return withRequestContext(businessPool, context, async (client: PoolClient) => {
        const result = await client.query<{ school_official_profile_read: unknown }>(
          "select api.school_official_profile_read() as school_official_profile_read",
        );
        const row = result.rows[0]?.school_official_profile_read;
        if (!row) throw new Error("School not found");
        return projectSchoolCardIdentity(row);
      });
    },

    async updateOfficialProfile(context: RequestContext, input: SchoolOfficialProfileInput): Promise<SchoolCardIdentity> {
      return withRequestContext(businessPool, context, async (client: PoolClient) => {
        const result = await client.query<{ school_official_profile_update: unknown }>(
          "select api.school_official_profile_update($1::jsonb) as school_official_profile_update",
          [JSON.stringify(input)],
        );
        const row = result.rows[0]?.school_official_profile_update;
        if (!row) throw new Error("School not found");
        return projectSchoolCardIdentity(row);
      });
    },
  };
}

export type SchoolNativeService = ReturnType<typeof createSchoolNativeService>;

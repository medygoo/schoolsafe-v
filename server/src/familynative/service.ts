// SchoolSafe Familles — service natif PostgreSQL (VPS).
// Cycle des accrédités externes à la récupération (spec V05–V11) :
// demande du principal, validation scolaire avec photo et slot borné,
// retrait à effet immédiat, liste des deux groupes pour le gardien.
// Toute requête s'exécute dans withRequestContext ; le serveur transporte
// la session, il ne recalcule jamais les permissions.
import type { PoolClient } from "pg";
import type { BusinessPool } from "../db/pool.js";
import { withRequestContext, type RequestContext } from "../db/context.js";
import { SchoolSafeError } from "../http/errors.js";

export function createFamilyNativeService(businessPool: BusinessPool) {
  function fail(error: unknown): never {
    const message = error instanceof Error ? error.message : "";
    if (message.includes("Access denied") || message.includes("permission denied") || message.includes("capability denied")) {
      throw new SchoolSafeError(403, "ACCESS_DENIED", "Permission refusée", false);
    }
    if (message.includes("PHOTO_NOT_VALIDATED") || message.includes("PHOTO_REQUIRED")) {
      throw new SchoolSafeError(400, "VALIDATION_INVALID", "Photographie non validée", false);
    }
    if (message.includes("PICKUP_LIMIT_REACHED")) {
      throw new SchoolSafeError(400, "VALIDATION_INVALID", "Trois accréditations actives déjà atteintes", false);
    }
    if (message.includes("Revoked authorization")) {
      throw new SchoolSafeError(400, "VALIDATION_INVALID", "Une révocation exige une nouvelle demande", false);
    }
    if (message.includes("not found") || message.includes("introuvable") || message.includes("Not found")) {
      throw new SchoolSafeError(404, "NOT_FOUND", "Introuvable", false);
    }
    throw error;
  }

  return {
    async linkGuardian(context: RequestContext, input: {
      profile_id: string;
      student_id: string;
      guardian_type: "pere" | "mere" | "tuteur";
      is_primary: boolean;
    }): Promise<string> {
      return withRequestContext(businessPool, context, async (client: PoolClient) => {
        const r = await client.query(
          "select api.family_guardian_link($1, $2, $3, $4) as result",
          [input.profile_id, input.student_id, input.guardian_type, input.is_primary],
        );
        return r.rows[0].result as string;
      });
    },

    async pickupAuthorizationRequest(context: RequestContext, input: {
      student_id: string;
      guardian_id: string;
      reason?: string;
      starts_on?: string;
      ends_on?: string;
    }): Promise<Record<string, unknown>> {
      return withRequestContext(businessPool, context, async (client: PoolClient) => {
        const r = await client.query(
          "select api.pickup_authorization_request($1, $2, $3, $4, $5) as result",
          [input.student_id, input.guardian_id, input.reason ?? null, input.starts_on ?? null, input.ends_on ?? null],
        );
        return r.rows[0].result as Record<string, unknown>;
      });
    },

    async pickupAuthorizationValidate(context: RequestContext, authorizationId: string): Promise<Record<string, unknown>> {
      return withRequestContext(businessPool, context, async (client: PoolClient) => {
        const r = await client.query("select api.pickup_authorization_validate($1) as result", [authorizationId]);
        return r.rows[0].result as Record<string, unknown>;
      });
    },

    async pickupAuthorizationRevoke(context: RequestContext, input: {
      authorization_id: string;
      reason?: string;
      mode?: "revoked" | "suspended";
    }): Promise<Record<string, unknown>> {
      return withRequestContext(businessPool, context, async (client: PoolClient) => {
        const r = await client.query(
          "select api.pickup_authorization_revoke($1, $2, $3) as result",
          [input.authorization_id, input.reason ?? null, input.mode ?? "revoked"],
        );
        return r.rows[0].result as Record<string, unknown>;
      });
    },

    async pickupAuthorizationList(context: RequestContext, studentId: string): Promise<Record<string, unknown>> {
      return withRequestContext(businessPool, context, async (client: PoolClient) => {
        const r = await client.query("select api.pickup_authorization_list($1) as result", [studentId]);
        return r.rows[0].result as Record<string, unknown>;
      });
    },

    // V16/R16-R17 : confirmation de remise transactionnelle (gardien).
    async pickupConfirm(context: RequestContext, input: {
      student_id: string;
      guardian_id: string;
      request_key: string;
      note?: string;
      proof_token?: string;
    }): Promise<Record<string, unknown>> {
      let result: Record<string, unknown>;
      try {
        result = await withRequestContext(businessPool, context, async (client: PoolClient) => {
          const r = await client.query(
            "select api.pickup_confirm($1, $2, $3, $4, $5, $6) as result",
            [input.student_id, input.guardian_id, context.profileId, input.request_key, input.note ?? null, input.proof_token ?? null],
          );
          return r.rows[0].result as Record<string, unknown>;
        });
      } catch (error) {
        fail(error);
      }
      if (result.status === "denied") {
        throw new SchoolSafeError(403, "ACCESS_DENIED", "Remise refusée", false);
      }
      return result;
    },

    // V12/T04 : transfert atomique du responsable principal (école habilitée).
    async familyPrimaryTransfer(context: RequestContext, input: {
      student_id: string;
      new_guardian_id: string;
      reason: string;
    }): Promise<Record<string, unknown>> {
      return withRequestContext(businessPool, context, async (client: PoolClient) => {
        const r = await client.query(
          "select api.family_primary_transfer($1, $2, $3) as result",
          [input.student_id, input.new_guardian_id, input.reason],
        );
        return r.rows[0].result as Record<string, unknown>;
      });
    },

    async callJson(context: RequestContext, sql: string, params: unknown[]): Promise<Record<string, unknown>> {
      try {
        return await withRequestContext(businessPool, context, async (client: PoolClient) => {
          const r = await client.query(sql, params);
          const row = r.rows[0] as Record<string, unknown>;
          const value = row.result ?? Object.values(row)[0];
          return value as Record<string, unknown>;
        });
      } catch (error) {
        fail(error);
      }
    },

    schoolAuthorizationList(context: RequestContext, studentId: string) {
      return this.callJson(context, "select api.pickup_school_authorization_list($1) as result", [studentId]);
    },

    parentAuthorizationList(context: RequestContext, studentId: string) {
      return this.callJson(context, "select api.pickup_parent_authorization_list($1) as result", [studentId]);
    },

    parentSuspend(context: RequestContext, authorizationId: string, reason: string) {
      return this.callJson(context, "select api.pickup_parent_suspend($1, $2) as result", [authorizationId, reason]);
    },

    parentReactivationRequest(context: RequestContext, authorizationId: string, reason: string) {
      return this.callJson(context, "select api.pickup_parent_reactivation_request($1, $2) as result", [authorizationId, reason]);
    },

    reactivateAuthorization(context: RequestContext, authorizationId: string) {
      return this.callJson(context, "select api.pickup_authorization_reactivate($1) as result", [authorizationId]);
    },

    refuseReactivation(context: RequestContext, authorizationId: string, reason: string) {
      return this.callJson(context, "select api.pickup_reactivation_refuse($1, $2) as result", [authorizationId, reason]);
    },

    parentFamilySuspend(context: RequestContext, guardianId: string, reason: string) {
      return this.callJson(context, "select api.pickup_parent_family_suspend($1, $2) as result", [guardianId, reason]);
    },

    parentFamilyReactivationRequest(context: RequestContext, guardianId: string, reason: string) {
      return this.callJson(context, "select api.pickup_parent_family_reactivation_request($1, $2) as result", [guardianId, reason]);
    },

    decideFamilyReactivation(context: RequestContext, guardianId: string, decision: "approved" | "refused", reason?: string) {
      return this.callJson(context, "select api.pickup_family_reactivation_decide($1, $2, $3) as result", [guardianId, decision, reason ?? null]);
    },

    reviewPhoto(context: RequestContext, guardianId: string, decision: "accepted" | "rejected", note?: string) {
      return this.callJson(context, "select api.pickup_photo_review($1, $2, $3) as result", [guardianId, decision, note ?? null]);
    },

    registerExternal(context: RequestContext, studentId: string, payload: Record<string, unknown>) {
      return this.callJson(context, "select api.pickup_external_register($1, $2::jsonb) as result", [studentId, JSON.stringify(payload)]);
    },

    setPortrait(context: RequestContext, guardianId: string, photoPath: string) {
      return this.callJson(context, "select api.scan_control_portrait_set($1, $2) as result", [guardianId, photoPath]);
    },

    async portraitReadable(context: RequestContext, photoPath: string): Promise<boolean> {
      try {
        return await withRequestContext(businessPool, context, async (client: PoolClient) => {
          const r = await client.query("select api.pickup_portrait_readable($1) as result", [photoPath]);
          return r.rows[0].result === true;
        });
      } catch (error) {
        fail(error);
      }
    },
  };
}

export type FamilyNativeService = ReturnType<typeof createFamilyNativeService>;
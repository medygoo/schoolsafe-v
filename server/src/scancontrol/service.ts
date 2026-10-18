// Orchestrateur additif. Il appelle les moteurs Rosario existants.
// Il ne remplace pas scan, attendance_apply, pickup_confirm ni card_replace.
import { randomUUID } from "node:crypto";
import type { PoolClient } from "pg";
import type { BusinessPool } from "../db/pool.js";
import { withRequestContext, type RequestContext } from "../db/context.js";
import { SchoolSafeError, type ApiErrorCode } from "../http/errors.js";
import { parseQrPayload, signCardNumber, type SecurityService } from "../security/service.js";
import type { DeviceHubService } from "../devicehub/service.js";
import type { FamilyNativeService } from "../familynative/service.js";
import type { CardsNativeService } from "../cardsnative/service.js";

export type ScanMode = "entry" | "pre_exit" | "gate_exit" | "pickup" | "fees" | "verify";

const MODE_CAPABILITY: Record<ScanMode, string> = {
  entry: "SCAN_ENTRY",
  pre_exit: "SCAN_PRE_EXIT",
  gate_exit: "SCAN_GATE_EXIT",
  pickup: "SCAN_PICKUP",
  fees: "SCAN_FEES",
  verify: "SCAN_VERIFY",
};

type CardRow = {
  found: boolean;
  card_id?: string;
  card_number?: string;
  status?: string;
  student_id?: string | null;
  matricule?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  class_id?: string | null;
  class_name?: string | null;
  scope_visible?: boolean;
};

type PresenceRow = {
  arrived_at: string | null;
  left_at: string | null;
  currently_on_site: boolean;
  original_exit_time: string | null;
  original_operator_id: string | null;
  original_operator: string | null;
  original_event: string | null;
};

function asObject<T>(value: unknown): T {
  if (typeof value === "string" && (value.startsWith("{") || value.startsWith("[") || value.startsWith("\""))) {
    return JSON.parse(value) as T;
  }
  return value as T;
}

function secretOrThrow(): string {
  const secret = process.env.CARD_HMAC_SECRET;
  if (!secret) {
    throw new SchoolSafeError(503, "SCAN_ENGINE_UNAVAILABLE" as ApiErrorCode, "Le secret de signature n'est pas configuré. Aucune validation locale.", true);
  }
  return secret;
}

function refusedStatus(status: string | undefined): string {
  if (status === "lost") return "LOST";
  if (status === "replaced") return "REPLACED";
  if (status === "revoked") return "REVOKED";
  return (status || "UNKNOWN").toUpperCase();
}

export function createScanControlService(
  businessPool: BusinessPool,
  engines: {
    security?: SecurityService;
    deviceHub: DeviceHubService;
    family: FamilyNativeService;
    cards: CardsNativeService;
  },
) {
  async function sql<T>(context: RequestContext, text: string, params: unknown[] = []): Promise<T> {
    return withRequestContext(businessPool, context, async (client: PoolClient) => {
      const result = await client.query(text, params);
      return asObject<T>(result.rows[0]?.result);
    });
  }

  async function note(context: RequestContext, eventType: string, studentId: string | null, payload: Record<string, unknown>): Promise<void> {
    await sql(context, "select api.scan_control_note($1, $2, $3::jsonb) as result", [
      eventType,
      studentId,
      JSON.stringify(payload),
    ]);
  }

  async function cardByNumber(context: RequestContext, cardNumber: string, capability: string): Promise<CardRow> {
    return sql<CardRow>(context, "select api.scan_control_card_by_number($1, $2) as result", [cardNumber, capability]);
  }

  async function presence(context: RequestContext, studentId: string): Promise<PresenceRow> {
    return sql<PresenceRow>(context, "select api.scan_control_presence($1) as result", [studentId]);
  }

  async function requireLocation(context: RequestContext, locationId: string | undefined): Promise<string> {
    if (!locationId || !/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(locationId)) {
      throw new SchoolSafeError(400, "VALIDATION_INVALID", "Le lieu du scan est requis", false);
    }
    const allowed = await sql<boolean>(context, "select api.scan_control_location_check($1) as result", [locationId]);
    if (allowed !== true) {
      throw new SchoolSafeError(400, "VALIDATION_INVALID", "Le lieu du scan n'est pas autorisé", false);
    }
    return locationId;
  }

  async function requireEngine(): Promise<SecurityService> {
    if (!engines.security) {
      throw new SchoolSafeError(503, "SCAN_ENGINE_UNAVAILABLE" as ApiErrorCode, "Le moteur de scan Rosario n'est pas joignable. Aucune validation locale.", true);
    }
    return engines.security;
  }

  function parseOrInvalid(qr: string): { cardNumber: string; signature: string } | null {
    const parsed = parseQrPayload(qr);
    if (!parsed) return null;
    const expected = signCardNumber(parsed.cardNumber, secretOrThrow());
    if (expected !== parsed.signature) return null;
    return parsed;
  }

  function flattenPeople(view: Record<string, unknown> | null): Array<Record<string, unknown>> {
    if (!view) return [];
    const family = Array.isArray(view.family) ? view.family as Array<Record<string, unknown>> : [];
    const accredited = Array.isArray(view.accredited) ? view.accredited as Array<Record<string, unknown>> : [];
    return [
      ...family.map((person) => ({
        id: person.guardian_id,
        name: person.name,
        relation: person.type,
        photo_path: person.photo_path,
        pickup_ready: person.pickup_ready === true,
        photo_accepted: person.photo_accepted === true,
        status: person.pickup_ready === true ? "ready" : "blocked",
      })),
      ...accredited.map((person) => ({
        id: person.guardian_id,
        name: person.name,
        relation: "accredited",
        photo_path: person.photo_path,
        pickup_ready: person.pickup_ready === true,
        photo_accepted: person.photo_accepted === true,
        status: typeof person.status === "string" ? person.status : "blocked",
      })),
    ];
  }

  async function applyAttendance(context: RequestContext): Promise<void> {
    await engines.deviceHub.attendanceApply(context);
  }

  return {
    async locations(context: RequestContext): Promise<{ locations: Array<{ id: string; code: string; label: string; kind: string }> }> {
      return withRequestContext(businessPool, context, async (client) => {
        const result = await client.query("select id, code, label, kind from api.scan_control_location_list()");
        return { locations: result.rows };
      });
    },

    async capabilities(context: RequestContext): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_my_capabilities() as result");
    },

    async grant(context: RequestContext, input: { profile_id: string; capability: string; action: "assign" | "revoke"; reason: string }): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_grant_set($1, $2, $3, $4) as result", [
        input.profile_id, input.capability, input.action, input.reason,
      ]);
    },

    async grantList(context: RequestContext): Promise<unknown> {
      return sql(context, "select api.scan_control_grant_list() as result");
    },

    async teacherBoard(context: RequestContext): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_teacher_board() as result");
    },

    async schoolBoard(context: RequestContext): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_school_board() as result");
    },

    async onSiteStudent(context: RequestContext, studentId: string): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_on_site_student($1) as result", [studentId]);
    },

    async onSiteClass(context: RequestContext, classId: string): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_on_site_class($1) as result", [classId]);
    },

    async onSiteSchool(context: RequestContext): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_on_site_school() as result");
    },

    async audit(context: RequestContext, studentId: string): Promise<unknown> {
      return sql(context, "select api.scan_control_audit_read($1) as result", [studentId]);
    },

    async transitionCampaign(context: RequestContext, campaignId: string, status: "published" | "closed" | "cancelled"): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_campaign_transition($1, $2) as result", [campaignId, status]);
    },

    async setPortrait(context: RequestContext, guardianId: string, photoPath: string): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_portrait_set($1, $2) as result", [guardianId, photoPath]);
    },

    async canonicalReplace(context: RequestContext, oldCardId: string): Promise<Record<string, unknown>> {
      const secret = secretOrThrow();
      const cardNumber = `SS-${context.schoolId.slice(0, 8)}-R-${Date.now()}`;
      const signature = signCardNumber(cardNumber, secret);
      const replaced = await sql<Record<string, unknown>>(context, "select api.scan_control_card_replace($1, $2, $3, $4) as result", [
        oldCardId, cardNumber, signature, randomUUID(),
      ]);
      return {
        ...replaced,
        qr_payload: `schoolsafe://card/${cardNumber}/${signature}`,
      };
    },

    async revokeCard(context: RequestContext, cardId: string, reason: string): Promise<Record<string, unknown>> {
      return sql(context, "select api.scan_control_card_revoke($1, $2) as result", [cardId, reason]);
    },

    async issueCard(context: RequestContext, studentId: string): Promise<Record<string, unknown>> {
      await sql(context, "select api.scan_control_require($1) as result", ["CARD_CREATE"]);
      return engines.cards.issueCardCredential(context, studentId);
    },

    async reportLoss(context: RequestContext, input: { student_id: string; card_id?: string; reason: string }): Promise<Record<string, unknown>> {
      await sql(context, "select api.scan_control_require($1) as result", ["CARD_LOST"]);
      return engines.cards.lossReport(context, input);
    },

    async reprint(context: RequestContext, cardId: string, reason: string): Promise<Record<string, unknown>> {
      await sql(context, "select api.scan_control_require($1) as result", ["CARD_REPRINT"]);
      return engines.cards.reprintAuthorize(context, cardId, reason);
    },

    async feeModule(context: RequestContext): Promise<Record<string, unknown>> {
      return sql(context, "select api.scanner_fee_module_get() as result");
    },

    async feeModuleSet(context: RequestContext, enabled: boolean): Promise<Record<string, unknown>> {
      return sql(context, "select api.scanner_fee_module_set($1) as result", [enabled]);
    },

    async feeDelegations(context: RequestContext): Promise<unknown> {
      return sql(context, "select api.scanner_fee_delegation_list() as result");
    },

    async feeDelegationSet(context: RequestContext, input: {
      profile_id: string;
      capability: "SCAN_FEES" | "SCAN_FEES_DELEGATE";
      action: "assign" | "revoke";
      reason: string;
      expires_at?: string | null;
      class_ids?: string[];
      campaign_id?: string | null;
    }): Promise<Record<string, unknown>> {
      return sql(context, "select api.scanner_fee_delegation_set($1, $2, $3, $4, $5, $6::uuid[], $7) as result", [
        input.profile_id,
        input.capability,
        input.action,
        input.reason,
        input.expires_at ?? null,
        input.class_ids ?? null,
        input.campaign_id ?? null,
      ]);
    },

    async scan(context: RequestContext, input: {
      mode: ScanMode;
      qr_payload: string;
      location_id?: string;
      authorized_person_id?: string;
      campaign_id?: string;
      justification?: string;
      note?: string;
      human_confirmed?: boolean;
      proof_token?: string;
    }): Promise<Record<string, unknown>> {
      if (input.mode === "pre_exit" || input.mode === "gate_exit") {
        throw new SchoolSafeError(400, "VALIDATION_INVALID", "Ce mode n'est pas un accès officiel du scanner", false);
      }
      const mode: ScanMode = input.mode;
      const capability = MODE_CAPABILITY[mode];
      if (mode !== "fees") {
        await sql(context, "select api.scan_control_require($1) as result", [capability]);
      }
      const parsed = parseOrInvalid(input.qr_payload);
      if (!parsed) {
        await note(context, "scan_control.verify", null, {
          mode,
          result: "INVALID",
          reason: "invalid_qr",
          decision: "denied",
        });
        return { result: "INVALID", signature_valid: false, reason: "invalid_qr" };
      }

      const card = await sql<CardRow & { signature_matches?: boolean; operational?: boolean }>(
        context,
        "select api.scanner_card_lookup($1, $2, $3) as result",
        [parsed.cardNumber, parsed.signature, capability],
      );
      if (!card.found) {
        await note(context, "scan_control.verify", null, {
          mode,
          card_number: parsed.cardNumber,
          result: "INVALID",
          reason: "card_not_found",
          decision: "denied",
        });
        return { result: "INVALID", signature_valid: true, reason: "card_not_found" };
      }
      if (card.signature_matches !== true) {
        await note(context, "scan_control.verify", null, {
          mode,
          result: "INVALID",
          reason: "signature_mismatch",
          decision: "denied",
        });
        return { result: "INVALID", signature_valid: false, reason: "signature_mismatch" };
      }
      if (!card.student_id) {
        await note(context, "scan_control.verify", null, {
          mode,
          result: "INVALID",
          reason: "student_missing",
          decision: "denied",
        });
        return { result: "INVALID", signature_valid: true, reason: "student_missing", status: card.status };
      }

      const identity = {
        student_id: card.student_id,
        student_name: `${card.first_name ?? ""} ${card.last_name ?? ""}`.trim(),
        class_name: card.class_name,
      };
      const base = {
        mode,
        card_id: card.card_id,
        card_number: card.card_number,
        student_id: card.student_id,
        authorized_person_id: input.authorized_person_id ?? null,
      };

      if (mode === "verify") {
        const decision = card.status !== "active"
          ? refusedStatus(card.status)
          : card.operational === true ? "VALID" : "UNAVAILABLE";
        await note(context, "scan_control.verify", card.student_id, {
          ...base,
          result: decision,
          decision: decision === "VALID" ? "allowed" : "denied",
          reason: decision === "VALID" ? null : card.status,
        });
        return {
          result: decision,
          signature_valid: true,
          status: card.status,
          card_id: card.card_id,
          ...identity,
        };
      }

      if (card.status !== "active") {
        if (mode === "fees") {
          const gate = await sql<Record<string, unknown>>(
            context,
            "select api.scanner_fee_authorize($1, $2) as result",
            [card.student_id, input.campaign_id ?? null],
          );
          if (gate.result !== "ALLOWED") {
            await note(context, "scan_control.fees", null, {
              mode,
              result: gate.result,
              decision: "denied",
            });
            return { result: String(gate.result || "NOT_DELEGATED"), signature_valid: true, decision: "denied" };
          }
        }
        await note(context, "scan_control.refused", card.student_id, {
          ...base,
          result: refusedStatus(card.status),
          decision: "denied",
          reason: card.status,
        });
        const refusal = {
          result: refusedStatus(card.status),
          signature_valid: true,
          status: card.status,
        };
        if (mode === "fees") return refusal;
        return { ...refusal, ...identity };
      }
      if (card.operational !== true && mode !== "fees") {
        await note(context, "scan_control.refused", card.student_id, {
          ...base,
          result: "STUDENT_NOT_OPERATIONAL",
          decision: "denied",
        });
        return { result: "STUDENT_NOT_OPERATIONAL", signature_valid: true, ...identity };
      }

      if (mode === "fees") {
        const fees = await sql<Record<string, unknown>>(
          context,
          "select api.scanner_fee_consult($1, $2) as result",
          [card.student_id, input.campaign_id ?? null],
        );
        if (fees.result !== "FEE_STATUS") {
          return { result: fees.result, signature_valid: true, decision: "denied" };
        }
        return { ...fees, signature_valid: true };
      }

      if (mode === "entry") {
        const locationId = await requireLocation(context, input.location_id);
        const recorded = await sql<Record<string, unknown>>(
          context,
          "select api.scanner_entry_record($1, $2, $3) as result",
          [card.card_id, card.student_id, locationId],
        );
        if (recorded.result === "PRESENT") {
          const after = await presence(context, card.student_id);
          await note(context, "scan_control.entry", card.student_id, {
            ...base, result: "PRESENT", decision: "allowed",
          });
          return {
            result: "PRESENT",
            decision: "allowed",
            event_id: recorded.event_id,
            occurred_at: recorded.occurred_at,
            currently_on_site: after.currently_on_site,
            ...identity,
            student_name: recorded.student_name ?? identity.student_name,
            class_name: recorded.class_name ?? identity.class_name,
          };
        }
        await note(context, "scan_control.entry", card.student_id, {
          ...base, result: recorded.result, decision: "denied",
        });
        return { ...recorded, decision: "denied", signature_valid: true, ...identity };
      }

      if (mode === "pickup") {
        const locationId = await requireLocation(context, input.location_id);
        if (!input.authorized_person_id) {
          const peoplePayload = await sql<Record<string, unknown>>(
            context,
            "select api.scanner_pickup_people($1, $2) as result",
            [card.student_id, locationId],
          );
          if (typeof peoplePayload.result === "string") {
            return { result: peoplePayload.result, decision: "denied", signature_valid: true, ...identity };
          }
          return {
            result: "PICKUP_CHOICE",
            decision: "pending",
            people: flattenPeople(peoplePayload),
            ...identity,
          };
        }
        if (input.human_confirmed !== true) {
          const prepared = await sql<Record<string, unknown>>(
            context,
            "select api.scanner_pickup_prepare($1, $2, $3, $4) as result",
            [card.student_id, card.card_id, locationId, input.authorized_person_id],
          );
          if (prepared.result !== "PREPARED") {
            await note(context, "scan_control.pickup", card.student_id, {
              mode, result: prepared.result, decision: "denied",
            });
            return { result: String(prepared.result || "PICKUP_RESTRICTED"), decision: "denied", signature_valid: true, ...identity };
          }
          await note(context, "scan_control.pickup", null, {
            mode, result: "NEED_HUMAN_CONFIRMATION", decision: "pending",
          });
          return {
            result: "NEED_HUMAN_CONFIRMATION",
            decision: "pending",
            proof_token: prepared.proof_token,
            ...identity,
          };
        }
        if (!input.proof_token) {
          await note(context, "scan_control.pickup", null, {
            mode, result: "PICKUP_PROOF_REQUIRED", decision: "denied",
          });
          return { result: "PICKUP_PROOF_REQUIRED", decision: "denied", signature_valid: true };
        }
        const confirmed = await sql<Record<string, unknown>>(
          context,
          "select api.scanner_pickup_confirm($1, $2, $3, $4, $5, $6) as result",
          [card.student_id, input.authorized_person_id, locationId, randomUUID(), input.note ?? null, input.proof_token],
        );
        if (confirmed.status === "confirmed" || confirmed.status === "already_confirmed") {
          const state = await presence(context, card.student_id);
          if (state.currently_on_site) {
            await sql(context, "select api.scan_control_presence_mark($1, 'check_out', $2) as result", [
              card.student_id, null,
            ]);
          }
          await note(context, "scan_control.pickup", card.student_id, {
            ...base, result: "CONFIRMED", decision: "allowed", basis: confirmed.basis ?? null,
          });
          return {
            result: "CONFIRMED",
            decision: "allowed",
            basis: confirmed.basis ?? null,
            guardian_name: confirmed.guardian_name ?? null,
            ...identity,
          };
        }
        await note(context, "scan_control.pickup", card.student_id, {
          ...base, result: "DENIED", decision: "denied", reason: confirmed.code ?? null,
        });
        return {
          result: "DENIED",
          decision: "denied",
          reason: confirmed.code ?? "PICKUP_RESTRICTED",
          ...identity,
        };
      }

      throw new SchoolSafeError(400, "VALIDATION_INVALID", "Mode de scan inconnu", false);
    },
  };
}

export type ScanControlService = ReturnType<typeof createScanControlService>;

// SchoolSafe Control — service d'impression de cartes côté serveur SchoolSafe.
// Envoie des demandes à Control App et interroge le statut.
import type { BusinessPool } from "../db/pool.js";
import { pushCardPrintRequest, type ControlAppConfig } from "../control-app/client.js";

export interface CardPrintRequestProjection {
  id: string;
  school_id: string;
  student_id: string;
  student_name: string;
  class_name: string;
  academic_year: string;
  status: string;
  format: string;
  is_duplicate: boolean;
  created_at: string;
  printed_at?: string;
}

export function createControlPrintNativeService(
  businessPool: BusinessPool,
  controlConfig?: ControlAppConfig,
) {
  return {
    /** Envoyer une demande d'impression à Control App */
    async submitPrintRequest(input: {
      student_id: string;
      student_name: string;
      class_name: string;
      academic_year: string;
      front_key: string;
      back_key: string;
      front_signed_url: string;
      back_signed_url: string;
      signed_url_expires_at: string;
      format: "badge" | "carte";
      is_duplicate?: boolean;
      metadata?: Record<string, unknown>;
    }): Promise<{ id: string } | null> {
      if (!controlConfig) return null;

      const schoolId = await (async () => {
        const r = await businessPool.query<{ school_id: string }>(
          "select iam.current_school_id() as school_id",
        );
        return r.rows[0]?.school_id;
      })();

      if (!schoolId) return null;

      return pushCardPrintRequest(controlConfig, {
        school_id: schoolId,
        student_id: input.student_id,
        student_name: input.student_name,
        class_name: input.class_name,
        academic_year: input.academic_year,
        front_key: input.front_key,
        back_key: input.back_key,
        front_signed_url: input.front_signed_url,
        back_signed_url: input.back_signed_url,
        signed_url_expires_at: input.signed_url_expires_at,
        format: input.format,
        version: 1,
        is_duplicate: input.is_duplicate ?? false,
        metadata: input.metadata,
      });
    },

    /** Liste des demandes d'impression */
    async listPrintRequests(limit = 50, offset = 0): Promise<CardPrintRequestProjection[]> {
      const r = await businessPool.query(
        `select id, school_id, student_id, student_name, class_name,
                academic_year, status, format, is_duplicate, created_at, printed_at
         from app.card_print_requests
         order by created_at desc
         limit $1 offset $2`,
        [limit, offset],
      );
      return r.rows;
    },
  };
}

export type ControlPrintNativeService = ReturnType<typeof createControlPrintNativeService>;
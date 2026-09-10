// SchoolSafe Control — routes d'impression de cartes.
import type { FastifyInstance } from "fastify";
import { SchoolSafeError } from "../http/errors.js";
import { newRequestId } from "../http/request-id.js";
import { requireAuthSession } from "../authnative/middleware.js";
import type { AuthNativeService } from "../authnative/service.js";
import type { ControlPrintNativeService } from "./service.js";
import type { BusinessPool } from "../db/pool.js";
import { z } from "zod";

export type ControlPrintNativeRouteDependencies = {
  authService: AuthNativeService;
  service: ControlPrintNativeService;
  businessPool: BusinessPool;
};

export function registerControlPrintNativeRoutes(
  app: FastifyInstance,
  dependencies: ControlPrintNativeRouteDependencies,
): void {
  const requireSession = requireAuthSession(dependencies.authService);

  // Demander l'impression d'une carte
  app.post("/native/control/print-request", { preHandler: requireSession }, async (request) => {
    const body = z.object({
      student_id: z.string().uuid(),
      student_name: z.string().min(1),
      class_name: z.string().min(1),
      academic_year: z.string().min(1),
      front_key: z.string().min(1),
      back_key: z.string().min(1),
      front_signed_url: z.string().url(),
      back_signed_url: z.string().url(),
      signed_url_expires_at: z.string(),
      format: z.enum(["badge", "carte"]).default("carte"),
      is_duplicate: z.boolean().default(false),
      metadata: z.record(z.unknown()).optional(),
    }).parse(request.body);

    const result = await dependencies.service.submitPrintRequest(body);
    if (!result) {
      throw new SchoolSafeError(503, "CONTROL_UNAVAILABLE",
        "Service d'impression non configuré ou indisponible", true);
    }
    return { data: result, request_id: newRequestId() };
  });

  // Liste des demandes d'impression
  app.get("/native/control/print-requests", { preHandler: requireSession }, async (request) => {
    const q = z.object({
      limit: z.coerce.number().int().positive().default(50),
      offset: z.coerce.number().int().min(0).default(0),
    }).parse(request.query ?? {});
    const data = await dependencies.service.listPrintRequests(q.limit, q.offset);
    return { data, request_id: newRequestId() };
  });

  // Impression rapide pour un élève
  app.post("/native/control/students/:studentId/print", { preHandler: requireSession }, async (request) => {
    const { studentId } = request.params as { studentId: string };
    const body = z.object({
      academic_year: z.string().min(1),
      format: z.enum(["badge", "carte"]).default("carte"),
    }).parse(request.body);

    const student = (await dependencies.businessPool.query(
      `select s.first_name || ' ' || s.last_name as student_name,
              c.name as class_name
       from app.students s
       left join app.classes c on c.id = s.class_id
       where s.id = $1`,
      [studentId],
    )).rows[0] as { student_name: string; class_name: string } | undefined;

    if (!student) throw new SchoolSafeError(404, "NOT_FOUND", "Élève introuvable", false);

    const unsignedUrl = `https://control.schoolsafe.local/templates/${body.format}-default.png`;
    const result = await dependencies.service.submitPrintRequest({
      student_id: studentId,
      student_name: student.student_name,
      class_name: student.class_name,
      academic_year: body.academic_year,
      front_key: "template-default-front",
      back_key: "template-default-back",
      front_signed_url: unsignedUrl,
      back_signed_url: unsignedUrl,
      signed_url_expires_at: new Date(Date.now() + 3600000).toISOString(),
      format: body.format,
      is_duplicate: false,
    });

    if (!result) {
      throw new SchoolSafeError(503, "CONTROL_UNAVAILABLE",
        "Service d'impression non configuré", true);
    }
    return { data: result, request_id: newRequestId() };
  });
}
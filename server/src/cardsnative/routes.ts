// SchoolSafe Cartes v1 — routes HTTP natives pour l'impression de cartes.
import type { FastifyInstance } from "fastify";
import { SchoolSafeError } from "../http/errors.js";
import { newRequestId } from "../http/request-id.js";
import { requireAuthSession } from "../authnative/middleware.js";
import type { AuthNativeService } from "../authnative/service.js";
import type { CardsNativeService } from "./service.js";
import { z } from "zod";

export type CardsNativeRouteDependencies = {
  authService: AuthNativeService;
  service: CardsNativeService;
};

export function registerCardsNativeRoutes(
  app: FastifyInstance,
  dependencies: CardsNativeRouteDependencies,
): void {
  const requireSession = requireAuthSession(dependencies.authService);

  // Soumettre une demande d'impression complète (avec images base64)
  app.post("/native/cards/print-request", { preHandler: requireSession }, async (request) => {
    const body = z.object({
      student_id: z.string().uuid(),
      format: z.enum(["badge", "carte"]),
      front_image_base64: z.string().min(50),
      back_image_base64: z.string().min(50),
      metadata: z.record(z.unknown()).optional(),
    }).parse(request.body);

    const result = await dependencies.service.submitFullPrintRequest(body);
    return { data: result, request_id: newRequestId() };
  });

  // Soumettre pour un élève spécifique (route raccourcie)
  app.post("/native/cards/students/:studentId/print", { preHandler: requireSession }, async (request) => {
    const { studentId } = request.params as { studentId: string };
    const body = z.object({
      format: z.enum(["badge", "carte"]).default("carte"),
      front_image_base64: z.string().min(50),
      back_image_base64: z.string().min(50),
      metadata: z.record(z.unknown()).optional(),
    }).parse(request.body);

    const result = await dependencies.service.submitFullPrintRequest({
      student_id: studentId,
      format: body.format,
      front_image_base64: body.front_image_base64,
      back_image_base64: body.back_image_base64,
      metadata: body.metadata,
    });

    return { data: result, request_id: newRequestId() };
  });

  // Liste des demandes d'impression
  app.get("/native/cards/print-requests", { preHandler: requireSession }, async (request) => {
    const q = z.object({
      status: z.string().optional(),
      limit: z.coerce.number().int().positive().default(50),
      offset: z.coerce.number().int().min(0).default(0),
    }).parse(request.query ?? {});

    const data = await dependencies.service.listPrintRequests(q.status, q.limit, q.offset);
    return { data, request_id: newRequestId() };
  });

  // Config de design des classes pour les cartes
  app.get("/native/cards/class-card-config", { preHandler: requireSession }, async () => {
    const data = await dependencies.service.classCardConfigList();
    return { data, request_id: newRequestId() };
  });

  // Compteurs
  app.get("/native/cards/print-requests/counts", { preHandler: requireSession }, async () => {
    const data = await dependencies.service.getCounts();
    return { data, request_id: newRequestId() };
  });

  // Mettre à jour le statut (Control App callback)
  app.patch("/native/cards/print-requests/:id/status", { preHandler: requireSession }, async (request) => {
    const { id } = request.params as { id: string };
    const body = z.object({
      status: z.enum(["pending", "submitted", "printed", "failed"]),
      control_app_reference: z.string().optional(),
      error_message: z.string().optional(),
    }).parse(request.body);

    const ok = await dependencies.service.updatePrintRequestStatus(
      id, body.status, body.control_app_reference, body.error_message,
    );
    if (!ok) throw new SchoolSafeError(404, "NOT_FOUND", "Demande introuvable", false);
    return { data: { updated: true }, request_id: newRequestId() };
  });
}
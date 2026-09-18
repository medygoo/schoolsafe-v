// SchoolSafe Familles — routes HTTP natives pour les accrédités externes.
// Le contexte de requête est construit UNIQUEMENT depuis la session résolue
// côté serveur (jamais depuis le navigateur).
import type { FastifyInstance } from "fastify";
import { newRequestId } from "../http/request-id.js";
import { requireAuthSession } from "../authnative/middleware.js";
import type { AuthNativeService } from "../authnative/service.js";
import type { FamilyNativeService } from "./service.js";
import type { RequestContext } from "../db/context.js";
import { z } from "zod";

export type FamilyNativeRouteDependencies = {
  authService: AuthNativeService;
  service: FamilyNativeService;
};

export function registerFamilyNativeRoutes(
  app: FastifyInstance,
  dependencies: FamilyNativeRouteDependencies,
): void {
  const requireSession = requireAuthSession(dependencies.authService);

  function contextFrom(request: { authSession?: { userId: string; profileId: string; schoolId: string } }): RequestContext {
    const session = request.authSession!;
    return {
      userId: session.userId,
      profileId: session.profileId,
      schoolId: session.schoolId,
      requestId: newRequestId(),
    };
  }

  // Demander une accréditation — principal seul (V05)
  app.post("/native/family/pickup-authorizations", { preHandler: requireSession }, async (request) => {
    const body = z.object({
      student_id: z.string().uuid(),
      guardian_id: z.string().uuid(),
      reason: z.string().optional(),
      starts_on: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
      ends_on: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
    }).parse(request.body);

    const data = await dependencies.service.pickupAuthorizationRequest(contextFrom(request), body);
    return { data, request_id: newRequestId() };
  });

  // Valider une accréditation — école habilitée, photo + slot (V06/V08/T11)
  app.post("/native/family/pickup-authorizations/:id/validate", { preHandler: requireSession }, async (request) => {
    const { id } = request.params as { id: string };
    const data = await dependencies.service.pickupAuthorizationValidate(contextFrom(request), id);
    return { data, request_id: newRequestId() };
  });

  // Retirer / suspendre — effet immédiat (T10/V14)
  app.post("/native/family/pickup-authorizations/:id/revoke", { preHandler: requireSession }, async (request) => {
    const { id } = request.params as { id: string };
    const body = z.object({
      reason: z.string().optional(),
      mode: z.enum(["revoked", "suspended"]).default("revoked"),
    }).parse(request.body ?? {});

    const data = await dependencies.service.pickupAuthorizationRevoke(contextFrom(request), {
      authorization_id: id,
      reason: body.reason,
      mode: body.mode,
    });
    return { data, request_id: newRequestId() };
  });

  // Transfert atomique du responsable principal — école habilitée (V12/T04)
  app.post("/native/family/students/:studentId/primary-transfer", { preHandler: requireSession }, async (request) => {
    const { studentId } = request.params as { studentId: string };
    const body = z.object({
      new_guardian_id: z.string().uuid(),
      reason: z.string().min(3),
    }).parse(request.body ?? {});

    const data = await dependencies.service.familyPrimaryTransfer(contextFrom(request), {
      student_id: studentId,
      new_guardian_id: body.new_guardian_id,
      reason: body.reason,
    });
    return { data, request_id: newRequestId() };
  });

  // Liste des deux groupes pour l'écran du gardien (§7.1)
  app.get("/native/family/students/:studentId/pickup-authorizations", { preHandler: requireSession }, async (request) => {
    const { studentId } = request.params as { studentId: string };
    const data = await dependencies.service.pickupAuthorizationList(contextFrom(request), studentId);
    return { data, request_id: newRequestId() };
  });
}
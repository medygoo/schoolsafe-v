// SchoolSafe Familles — routes HTTP natives pour les accrédités externes.
// Le contexte de requête est construit UNIQUEMENT depuis la session résolue
// côté serveur (jamais depuis le navigateur).
import type { FastifyInstance } from "fastify";
import { createReadStream } from "node:fs";
import path from "node:path";
import { newRequestId } from "../http/request-id.js";
import { requireAuthSession } from "../authnative/middleware.js";
import type { AuthNativeService } from "../authnative/service.js";
import type { FamilyNativeService } from "./service.js";
import type { FamilyImportService } from "./import.js";
import type { RequestContext } from "../db/context.js";
import { SchoolSafeError } from "../http/errors.js";
import { assertLogoBytes, logoMime, removeNewLogo, resolveSchoolLogo } from "../schoolnative/logo.js";
import { writeAuthorizedPortrait } from "./portrait.js";
import { z } from "zod";

export type FamilyNativeRouteDependencies = {
  authService: AuthNativeService;
  service: FamilyNativeService;
  importService?: FamilyImportService;
  portraitDir?: string;
};

export function registerFamilyNativeRoutes(
  app: FastifyInstance,
  dependencies: FamilyNativeRouteDependencies,
): void {
  const requireSession = requireAuthSession(dependencies.authService);
  const portraitDir = dependencies.portraitDir ?? path.resolve(process.cwd(), "server/uploads/authorized-persons");
  const reasonSchema = z.object({ reason: z.string().trim().min(3).max(500) });
  const fileName = z.string().regex(/^[0-9a-f-]{36}\.(png|jpg|webp)$/i);

  function contextFrom(request: { authSession?: { userId: string; profileId: string; schoolId: string } }): RequestContext {
    const session = request.authSession!;
    return {
      userId: session.userId,
      profileId: session.profileId,
      schoolId: session.schoolId,
      requestId: newRequestId(),
    };
  }

  app.post("/native/family/guardians", { preHandler: requireSession }, async (request) => {
    const body = z.object({
      profile_id: z.string().uuid(),
      student_id: z.string().uuid(),
      guardian_type: z.enum(["pere", "mere", "tuteur"]),
      is_primary: z.boolean().default(false),
    }).parse(request.body);
    const data = await dependencies.service.linkGuardian(contextFrom(request), body);
    return { data: { id: data }, request_id: newRequestId() };
  });

  // Demander une accréditation — principal ou permission administrative (V05)
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

  // Confirmer une remise — gardien, revalidation serveur au moment de confirmer (V16/R16-R17)
  app.post("/native/family/pickup-confirm", { preHandler: requireSession }, async (request) => {
    const body = z.object({
      student_id: z.string().uuid(),
      guardian_id: z.string().uuid(),
      request_key: z.string().min(8),
      note: z.string().optional(),
      proof_token: z.string().min(16).optional(),
    }).parse(request.body);

    const data = await dependencies.service.pickupConfirm(contextFrom(request), body);
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

  // ————— Lot E : import collectif des élèves —————

  // Préparer un lot d'import (CSV texte, empreinte SHA-256, idempotent)
  app.post("/native/family/student-imports", { preHandler: requireSession }, async (request) => {
    if (!dependencies.importService) {
      return { data: { error_codes: ["Service d'import non configuré."] }, request_id: newRequestId() };
    }
    const body = z.object({
      filename: z.string().min(1).max(255),
      file_content: z.string().min(10).max(2 * 1024 * 1024),
    }).parse(request.body);

    const data = await dependencies.importService.prepare(contextFrom(request), {
        filename: body.filename,
        fileContent: body.file_content,
      });
    return { data, request_id: newRequestId() };
  });

  // Aperçu du lot — aucune écriture métier (§8.2)
  app.post("/native/family/student-imports/:jobId/preview", { preHandler: requireSession }, async (request) => {
    if (!dependencies.importService) {
      return { data: { error_codes: ["Service d'import non configuré."] }, request_id: newRequestId() };
    }
    const { jobId } = request.params as { jobId: string };
    const data = await dependencies.importService.preview(contextFrom(request), jobId);
    return { data, request_id: newRequestId() };
  });

  // Appliquer le lot confirmé — idempotent, matricule unique (T13)
  app.post("/native/family/student-imports/:jobId/commit", { preHandler: requireSession }, async (request) => {
    if (!dependencies.importService) {
      return { data: { error_codes: ["Service d'import non configuré."] }, request_id: newRequestId() };
    }
    const { jobId } = request.params as { jobId: string };
    const data = await dependencies.importService.commit(contextFrom(request), jobId);
    return { data, request_id: newRequestId() };
  });

  // Liste des deux groupes pour l'écran du gardien (§7.1)
  app.get("/native/family/students/:studentId/pickup-authorizations", { preHandler: requireSession }, async (request) => {
    const { studentId } = request.params as { studentId: string };
    const data = await dependencies.service.pickupAuthorizationList(contextFrom(request), studentId);
    return { data, request_id: newRequestId() };
  });

  app.get("/native/family/students/:studentId/pickup-authorizations/school", { preHandler: requireSession }, async (request) => {
    const { studentId } = z.object({ studentId: z.string().uuid() }).parse(request.params);
    const data = await dependencies.service.schoolAuthorizationList(contextFrom(request), studentId);
    return { data, request_id: newRequestId() };
  });

  app.get("/native/family/students/:studentId/pickup-authorizations/mine", { preHandler: requireSession }, async (request) => {
    const { studentId } = z.object({ studentId: z.string().uuid() }).parse(request.params);
    const data = await dependencies.service.parentAuthorizationList(contextFrom(request), studentId);
    return { data, request_id: newRequestId() };
  });

  app.post("/native/family/students/:studentId/accredited", { preHandler: requireSession }, async (request) => {
    const { studentId } = z.object({ studentId: z.string().uuid() }).parse(request.params);
    const body = z.object({
      full_name: z.string().trim().min(2).max(160),
      phone: z.string().trim().max(40).optional(),
      email: z.string().trim().max(160).optional(),
      address: z.string().trim().max(240).optional(),
      consent: z.string().trim().min(3).max(500),
      starts_on: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
      ends_on: z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
      photo_path: z.string().regex(/^\/uploads\/authorized-persons\/[A-Za-z0-9._/-]+$/).optional(),
    }).parse(request.body);
    const data = await dependencies.service.registerExternal(contextFrom(request), studentId, body);
    return { data, request_id: newRequestId() };
  });

  app.post("/native/family/guardians/:guardianId/portrait", { preHandler: requireSession }, async (request) => {
    const { guardianId } = z.object({ guardianId: z.string().uuid() }).parse(request.params);
    const context = contextFrom(request);
    const file = await request.file();
    if (!file) throw new SchoolSafeError(400, "FILE_MISSING", "Aucun fichier reçu", false);
    const chunks: Buffer[] = [];
    for await (const chunk of file.file) chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
    if (file.file.truncated) throw new SchoolSafeError(400, "FILE_TOO_LARGE", "Fichier trop volumineux (max 2 Mo)", false);
    const bytes = Buffer.concat(chunks);
    const image = assertLogoBytes(bytes, file.mimetype);
    const stored = await writeAuthorizedPortrait(portraitDir, context.schoolId, bytes, image);
    try {
      const data = await dependencies.service.setPortrait(context, guardianId, stored.photoPath);
      return { data, request_id: newRequestId() };
    } catch (error) {
      await removeNewLogo(stored.absolutePath);
      throw error;
    }
  });

  app.get("/native/family/portrait-file/:filename", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "private, no-store");
    const filename = fileName.parse((request.params as { filename: string }).filename);
    const context = contextFrom(request);
    const absolute = resolveSchoolLogo(portraitDir, context.schoolId, filename);
    if (!absolute) throw new SchoolSafeError(404, "NOT_FOUND", "Photographie introuvable", false);
    const photoPath = `/uploads/authorized-persons/${context.schoolId.toLowerCase()}/${filename.toLowerCase()}`;
    const readable = await dependencies.service.portraitReadable(context, photoPath);
    if (!readable) throw new SchoolSafeError(404, "NOT_FOUND", "Photographie introuvable", false);
    return reply.type(logoMime(filename.toLowerCase())).send(createReadStream(absolute));
  });

  app.post("/native/family/guardians/:guardianId/photo-review", { preHandler: requireSession }, async (request) => {
    const { guardianId } = z.object({ guardianId: z.string().uuid() }).parse(request.params);
    const body = z.object({
      decision: z.enum(["accepted", "rejected"]),
      note: z.string().trim().max(500).optional(),
    }).parse(request.body);
    const data = await dependencies.service.reviewPhoto(contextFrom(request), guardianId, body.decision, body.note);
    return { data, request_id: newRequestId() };
  });

  app.post("/native/family/pickup-authorizations/:id/parent-suspend", { preHandler: requireSession }, async (request) => {
    const { id } = z.object({ id: z.string().uuid() }).parse(request.params);
    const body = reasonSchema.parse(request.body);
    const data = await dependencies.service.parentSuspend(contextFrom(request), id, body.reason);
    return { data, request_id: newRequestId() };
  });

  app.post("/native/family/pickup-authorizations/:id/reactivation-request", { preHandler: requireSession }, async (request) => {
    const { id } = z.object({ id: z.string().uuid() }).parse(request.params);
    const body = z.object({ reason: z.string().trim().max(500).optional() }).parse(request.body ?? {});
    const data = await dependencies.service.parentReactivationRequest(contextFrom(request), id, body.reason ?? "");
    return { data, request_id: newRequestId() };
  });

  app.post("/native/family/pickup-authorizations/:id/reactivate", { preHandler: requireSession }, async (request) => {
    const { id } = z.object({ id: z.string().uuid() }).parse(request.params);
    const data = await dependencies.service.reactivateAuthorization(contextFrom(request), id);
    return { data, request_id: newRequestId() };
  });

  app.post("/native/family/pickup-authorizations/:id/reactivation-refuse", { preHandler: requireSession }, async (request) => {
    const { id } = z.object({ id: z.string().uuid() }).parse(request.params);
    const body = reasonSchema.parse(request.body);
    const data = await dependencies.service.refuseReactivation(contextFrom(request), id, body.reason);
    return { data, request_id: newRequestId() };
  });

  app.post("/native/family/guardians/:guardianId/parent-suspend", { preHandler: requireSession }, async (request) => {
    const { guardianId } = z.object({ guardianId: z.string().uuid() }).parse(request.params);
    const body = reasonSchema.parse(request.body);
    const data = await dependencies.service.parentFamilySuspend(contextFrom(request), guardianId, body.reason);
    return { data, request_id: newRequestId() };
  });

  app.post("/native/family/guardians/:guardianId/reactivation-request", { preHandler: requireSession }, async (request) => {
    const { guardianId } = z.object({ guardianId: z.string().uuid() }).parse(request.params);
    const body = z.object({ reason: z.string().trim().max(500).optional() }).parse(request.body ?? {});
    const data = await dependencies.service.parentFamilyReactivationRequest(contextFrom(request), guardianId, body.reason ?? "");
    return { data, request_id: newRequestId() };
  });

  app.post("/native/family/guardians/:guardianId/reactivation-decide", { preHandler: requireSession }, async (request) => {
    const { guardianId } = z.object({ guardianId: z.string().uuid() }).parse(request.params);
    const body = z.object({
      decision: z.enum(["approved", "refused"]),
      reason: z.string().trim().max(500).optional(),
    }).parse(request.body);
    const data = await dependencies.service.decideFamilyReactivation(contextFrom(request), guardianId, body.decision, body.reason);
    return { data, request_id: newRequestId() };
  });
}
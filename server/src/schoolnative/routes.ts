import { createReadStream } from "node:fs";
import path from "node:path";
import type { FastifyInstance } from "fastify";
import { z } from "zod";
import { requireAuthSession } from "../authnative/middleware.js";
import type { AuthNativeService } from "../authnative/service.js";
import type { RequestContext } from "../db/context.js";
import { SchoolSafeError } from "../http/errors.js";
import { newRequestId } from "../http/request-id.js";
import { assertLogoBytes, logoMime, removeNewLogo, resolveSchoolLogo, writeSchoolLogo } from "./logo.js";
import type { SchoolNativeService } from "./service.js";

export type SchoolNativeRouteDependencies = {
  authService: AuthNativeService;
  service: SchoolNativeService;
  logoDir?: string;
};

const optionalText = z.string().trim().max(500).optional().nullable();

const profileSchema = z.object({
  name: z.string().trim().min(1).max(200).optional(),
  logo_path: optionalText,
  address: optionalText,
  city: optionalText,
  province: optionalText,
  country: optionalText,
  phone: optionalText,
  email: z.string().trim().email().or(z.literal("")).optional().nullable(),
  website_url: z.string().trim().url().or(z.literal("")).optional().nullable(),
}).strict();

export function registerSchoolNativeRoutes(
  app: FastifyInstance,
  dependencies: SchoolNativeRouteDependencies,
): void {
  const requireSession = requireAuthSession(dependencies.authService);
  const logoDir = dependencies.logoDir ?? path.resolve(process.cwd(), "server/uploads/logos");

  function contextFrom(request: { authSession?: { userId: string; profileId: string; schoolId: string } }): RequestContext {
    const session = request.authSession!;
    return {
      userId: session.userId,
      profileId: session.profileId,
      schoolId: session.schoolId,
      requestId: newRequestId(),
    };
  }

  function rethrowProfile(error: unknown): never {
    if (error instanceof SchoolSafeError) throw error;
    const message = error instanceof Error ? error.message : "";
    if (message.includes("Access denied") || message.includes("permission denied")) {
      throw new SchoolSafeError(403, "ACCESS_DENIED", "Permission refusée", false);
    }
    if (message.includes("School not found")) {
      throw new SchoolSafeError(404, "SCHOOL_NOT_FOUND", "École introuvable", false);
    }
    if (message.includes("Invalid") || message.includes("required")) {
      throw new SchoolSafeError(400, "VALIDATION_INVALID", "Donnée invalide", false);
    }
    throw error;
  }

  app.get("/native/school/official-profile", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "no-store");
    try {
      const data = await dependencies.service.readOfficialProfile(contextFrom(request));
      return { data, request_id: newRequestId() };
    } catch (error) {
      rethrowProfile(error);
    }
  });

  app.put("/native/school/official-profile", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "no-store");
    const payload = profileSchema.parse(request.body ?? {});
    try {
      const data = await dependencies.service.updateOfficialProfile(contextFrom(request), {
        ...payload,
        email: payload.email === "" ? null : payload.email,
        website_url: payload.website_url === "" ? null : payload.website_url,
      });
      return { data, request_id: newRequestId() };
    } catch (error) {
      rethrowProfile(error);
    }
  });

  app.post("/native/school/logo", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "no-store");
    const context = contextFrom(request);
    try {
      await dependencies.service.readOfficialProfile(context);
    } catch (error) {
      rethrowProfile(error);
    }
    const file = await request.file();
    if (!file) throw new SchoolSafeError(400, "FILE_MISSING", "Aucun fichier reçu", false);
    const chunks: Buffer[] = [];
    for await (const chunk of file.file) chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
    if (file.file.truncated) throw new SchoolSafeError(400, "FILE_TOO_LARGE", "Fichier trop volumineux (max 2 Mo)", false);
    const bytes = Buffer.concat(chunks);
    const image = assertLogoBytes(bytes, file.mimetype);
    const stored = await writeSchoolLogo(logoDir, context.schoolId, bytes, image);
    try {
      await dependencies.service.updateOfficialProfile(context, { logo_path: stored.publicPath });
    } catch (error) {
      await removeNewLogo(stored.absolutePath);
      rethrowProfile(error);
    }
    return { logo_path: stored.publicPath, request_id: newRequestId() };
  });

  app.get("/native/school/logo-file/:filename", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "private, no-store");
    const filename = z.string().regex(/^[0-9a-f-]{36}\.(png|jpg|webp)$/i).parse((request.params as { filename: string }).filename);
    const absolute = resolveSchoolLogo(logoDir, contextFrom(request).schoolId, filename);
    if (!absolute) throw new SchoolSafeError(404, "NOT_FOUND", "Logo introuvable", false);
    return reply.type(logoMime(filename.toLowerCase())).send(createReadStream(absolute));
  });
}

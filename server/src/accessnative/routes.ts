import type { FastifyInstance, FastifyRequest } from "fastify";
import { z } from "zod";
import { requireAuthSession } from "../authnative/middleware.js";
import type { AuthNativeService } from "../authnative/service.js";
import { SchoolSafeError } from "../http/errors.js";
import { newRequestId } from "../http/request-id.js";
import type { AccessNativeService } from "./service.js";

const pageSchema = z.object({
  query: z.string().trim().max(100).default(""),
  limit: z.coerce.number().int().min(1).max(100).default(25),
  offset: z.coerce.number().int().min(0).max(1000000).default(0),
}).strict();
const profileSchema = z.object({ profileId: z.string().uuid() }).strict();
export type AccessNativeRouteDependencies = { authService: AuthNativeService; service: AccessNativeService };

export function registerAccessNativeRoutes(app: FastifyInstance, deps: AccessNativeRouteDependencies): void {
  const requireSession = requireAuthSession(deps.authService);
  function context(request: FastifyRequest) {
    const session = request.authSession!;
    return { userId: session.userId, profileId: session.profileId, schoolId: session.schoolId, requestId: newRequestId() };
  }
  app.get("/native/access/profiles", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "no-store");
    const input = pageSchema.parse(request.query);
    const actor = context(request);
    return { data: await deps.service.listProfiles(actor, input), request_id: actor.requestId };
  });
  app.get("/native/access/roles", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "no-store");
    const input = pageSchema.parse(request.query);
    const actor = context(request);
    return { data: await deps.service.listRoles(actor, input), request_id: actor.requestId };
  });
  app.get("/native/access/profiles/:profileId", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "no-store");
    const { profileId } = profileSchema.parse(request.params);
    z.object({}).strict().parse(request.query);
    const actor = context(request);
    const data = await deps.service.readProfile(actor, profileId);
    if (!data) throw new SchoolSafeError(404, "NOT_FOUND", "Profil introuvable", false);
    return { data, request_id: actor.requestId };
  });
}

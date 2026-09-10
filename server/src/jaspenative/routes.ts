// SchoolSafe — route JASPE 2.5D : point d'entrée unique du frontend.
// Session obligatoire quand le service d'auth est branché (mission section 9 :
// limite par utilisateur authentifié). Jamais de clé, jamais de SQL ici.
import type { FastifyInstance } from "fastify";
import { z } from "zod";
import { newRequestId } from "../http/request-id.js";
import { requireAuthSession } from "../authnative/middleware.js";
import type { AuthNativeService } from "../authnative/service.js";
import type { JaspeNativeService } from "./service.js";

const chatSchema = z.object({
  message: z.string().trim().min(1, "message requis").max(2000, "message trop long"),
});

export type JaspeNativeRouteDependencies = {
  service: JaspeNativeService;
  authService?: AuthNativeService;
};

export function registerJaspeNativeRoutes(
  app: FastifyInstance,
  dependencies: JaspeNativeRouteDependencies,
): void {
  const preHandlers = dependencies.authService
    ? [requireAuthSession(dependencies.authService)]
    : [];

  app.post("/native/jaspe/chat", { preHandler: preHandlers }, async (request) => {
    const body = chatSchema.parse(request.body ?? {});
    const session = request.authSession;
    const sessionKey = session
      ? `u:${session.userId}:${session.profileId}`
      : `ip:${request.ip}`;
    const result = await dependencies.service.chat({ message: body.message, sessionKey });
    return { data: { reply: result.reply }, request_id: newRequestId() };
  });
}

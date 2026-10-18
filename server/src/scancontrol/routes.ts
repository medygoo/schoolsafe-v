import type { FastifyInstance } from "fastify";
import { z } from "zod";
import { SchoolSafeError } from "../http/errors.js";
import { newRequestId } from "../http/request-id.js";
import { requireAuthSession } from "../authnative/middleware.js";
import type { AuthNativeService } from "../authnative/service.js";
import type { RequestContext } from "../db/context.js";
import type { ScanControlService, ScanMode } from "./service.js";

const modes = ["entry", "pickup", "fees", "verify"] as const;
const capabilities = [
  "SCAN_ENTRY", "SCAN_PRE_EXIT", "SCAN_GATE_EXIT", "SCAN_PICKUP", "SCAN_FEES", "SCAN_VERIFY",
  "CARD_CREATE", "CARD_PRINT", "CARD_LOST", "CARD_REPLACE", "CARD_REPRINT", "CARD_REVOKE",
  "SCAN_PICKUP_OVERRIDE",
] as const;

export type ScanControlRouteDependencies = {
  authService: AuthNativeService;
  service: ScanControlService;
};

function rethrow(error: unknown): never {
  if (error instanceof SchoolSafeError) throw error;
  const message = error instanceof Error ? error.message : "";
  if (message.includes("capability denied") || message.includes("Access denied")) {
    throw new SchoolSafeError(403, "ACCESS_DENIED", "Capacité non déléguée", false);
  }
  throw error;
}

export function registerScanControlRoutes(app: FastifyInstance, dependencies: ScanControlRouteDependencies): void {
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

  async function run<T>(work: () => Promise<T>): Promise<{ data: T; request_id: string }> {
    try {
      return { data: await work(), request_id: newRequestId() };
    } catch (error) {
      rethrow(error);
    }
  }

  app.get("/native/scan-control/locations", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "no-store");
    return run(() => dependencies.service.locations(contextFrom(request)));
  });

  app.get("/native/scan-control/capabilities", { preHandler: requireSession }, async (request) => {
    return run(() => dependencies.service.capabilities(contextFrom(request)));
  });

  app.get("/native/scan-control/grants", { preHandler: requireSession }, async (request) => {
    return run(() => dependencies.service.grantList(contextFrom(request)));
  });

  app.get("/native/scan-control/fees/module", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "no-store");
    return run(() => dependencies.service.feeModule(contextFrom(request)));
  });

  app.post("/native/scan-control/fees/module", { preHandler: requireSession }, async (request) => {
    const body = z.object({ enabled: z.boolean() }).strict().parse(request.body);
    return run(() => dependencies.service.feeModuleSet(contextFrom(request), body.enabled));
  });

  app.get("/native/scan-control/fees/delegations", { preHandler: requireSession }, async (request, reply) => {
    reply.header("Cache-Control", "no-store");
    return run(() => dependencies.service.feeDelegations(contextFrom(request)));
  });

  app.post("/native/scan-control/fees/delegations", { preHandler: requireSession }, async (request) => {
    const body = z.object({
      profile_id: z.string().uuid(),
      capability: z.enum(["SCAN_FEES", "SCAN_FEES_DELEGATE"]),
      action: z.enum(["assign", "revoke"]),
      reason: z.string().min(1).max(500),
      expires_at: z.string().min(10).max(40).nullable().optional(),
      class_ids: z.array(z.string().uuid()).max(50).optional(),
      campaign_id: z.string().uuid().nullable().optional(),
    }).strict().parse(request.body);
    return run(() => dependencies.service.feeDelegationSet(contextFrom(request), body));
  });

  app.post("/native/scan-control/grants", { preHandler: requireSession }, async (request) => {
    const body = z.object({
      profile_id: z.string().uuid(),
      capability: z.enum(capabilities),
      action: z.enum(["assign", "revoke"]),
      reason: z.string().min(1).max(500),
    }).parse(request.body);
    return run(() => dependencies.service.grant(contextFrom(request), body));
  });

  app.post("/native/scan-control/scan", { preHandler: requireSession }, async (request) => {
    const body = z.object({
      mode: z.enum(modes),
      qr_payload: z.string().min(1),
      location_id: z.string().uuid().optional(),
      authorized_person_id: z.string().uuid().optional(),
      campaign_id: z.string().uuid().optional(),
      justification: z.string().max(500).optional(),
      note: z.string().max(500).optional(),
      human_confirmed: z.boolean().optional(),
      proof_token: z.string().min(16).max(80).optional(),
    }).strict().parse(request.body);
    return run(() => dependencies.service.scan(contextFrom(request), { ...body, mode: body.mode as ScanMode }));
  });

  app.post("/native/scan-control/campaigns/:campaignId/publish", { preHandler: requireSession }, async (request) => {
    const { campaignId } = request.params as { campaignId: string };
    return run(() => dependencies.service.transitionCampaign(contextFrom(request), campaignId, "published"));
  });

  app.post("/native/scan-control/campaigns/:campaignId/close", { preHandler: requireSession }, async (request) => {
    const { campaignId } = request.params as { campaignId: string };
    return run(() => dependencies.service.transitionCampaign(contextFrom(request), campaignId, "closed"));
  });

  app.post("/native/scan-control/campaigns/:campaignId/cancel", { preHandler: requireSession }, async (request) => {
    const { campaignId } = request.params as { campaignId: string };
    return run(() => dependencies.service.transitionCampaign(contextFrom(request), campaignId, "cancelled"));
  });

  app.get("/native/scan-control/board/teacher", { preHandler: requireSession }, async (request) => {
    return run(() => dependencies.service.teacherBoard(contextFrom(request)));
  });

  app.get("/native/scan-control/board/school", { preHandler: requireSession }, async (request) => {
    return run(() => dependencies.service.schoolBoard(contextFrom(request)));
  });

  app.get("/native/scan-control/on-site", { preHandler: requireSession }, async (request) => {
    const query = z.object({
      student_id: z.string().uuid().optional(),
      class_id: z.string().uuid().optional(),
    }).parse(request.query ?? {});
    const context = contextFrom(request);
    if (query.student_id) return run(() => dependencies.service.onSiteStudent(context, query.student_id!));
    if (query.class_id) return run(() => dependencies.service.onSiteClass(context, query.class_id!));
    return run(() => dependencies.service.onSiteSchool(context));
  });

  app.get("/native/scan-control/audit", { preHandler: requireSession }, async (request) => {
    const query = z.object({ student_id: z.string().uuid() }).parse(request.query ?? {});
    return run(() => dependencies.service.audit(contextFrom(request), query.student_id));
  });

  app.post("/native/scan-control/guardians/:guardianId/portrait", { preHandler: requireSession }, async (request) => {
    const { guardianId } = request.params as { guardianId: string };
    const body = z.object({ photo_path: z.string().min(8).max(240) }).parse(request.body);
    return run(() => dependencies.service.setPortrait(contextFrom(request), guardianId, body.photo_path));
  });

  app.post("/native/scan-control/cards/issue", { preHandler: requireSession }, async (request) => {
    const body = z.object({ student_id: z.string().uuid() }).parse(request.body);
    return run(() => dependencies.service.issueCard(contextFrom(request), body.student_id));
  });

  app.post("/native/scan-control/cards/loss", { preHandler: requireSession }, async (request) => {
    const body = z.object({
      student_id: z.string().uuid(),
      card_id: z.string().uuid().optional(),
      reason: z.string().min(1).max(500),
    }).parse(request.body);
    return run(() => dependencies.service.reportLoss(contextFrom(request), body));
  });

  app.post("/native/scan-control/cards/:cardId/canonical-replace", { preHandler: requireSession }, async (request) => {
    const { cardId } = request.params as { cardId: string };
    return run(() => dependencies.service.canonicalReplace(contextFrom(request), cardId));
  });

  app.post("/native/scan-control/cards/:cardId/reprint", { preHandler: requireSession }, async (request) => {
    const { cardId } = request.params as { cardId: string };
    const body = z.object({ reason: z.string().min(1).max(500) }).parse(request.body);
    return run(() => dependencies.service.reprint(contextFrom(request), cardId, body.reason));
  });

  app.post("/native/scan-control/cards/:cardId/revoke", { preHandler: requireSession }, async (request) => {
    const { cardId } = request.params as { cardId: string };
    const body = z.object({ reason: z.string().min(1).max(500) }).parse(request.body);
    return run(() => dependencies.service.revokeCard(contextFrom(request), cardId, body.reason));
  });
}

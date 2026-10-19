/**
 * SchoolSafe JASPE 2.5D — Worker Cloudflare.
 *
 * Pont entre le VPS SchoolSafe et Cloudflare AI.
 * Le VPS appelle ce Worker via JASPE_WORKER_URL. Le Worker ne fait JAMAIS de SQL,
 * il appelle uniquement Cloudflare AI (Workers AI via binding), protégé par AI Gateway.
 *
 * Bindings (wrangler.toml) :
 *   - AI (Workers AI)  -> le "cerveau" (ex: @cf/meta/llama-3.3-70b-instruct)
 *   - AI_GATEWAY (AI Gateway) -> contrôle des requêtes/quotas/latence
 * Le VPS n'a aucune clé ; tout se passe côté Worker.
 *
 * Suivi Respan (optionnel, inerte sans RESPAN_API_KEY) : métriques seulement
 * (durée, modèle, jetons, issue), jamais le message ni la réponse. Voir trace.ts.
 */

import { traceJaspe, type TraceContext, type TraceEnv } from "./trace.js";

export interface Env extends TraceEnv {
  AI: any;
  AI_GATEWAY?: any;
  JASPE_MODEL?: string;
  JASPE_SYSTEM_PROMPT?: string;
  CONTROL_INSTANCE_ID?: string;
  JASPE_ALLOWED_ORIGINS?: string;
  JASPE_WORKER_HMAC_SECRET: string;
}

interface ChatRequest {
  message?: string;
  session_key?: string;
}

const REPLAY_WINDOW_SECONDS = 300;

function bytesToHex(bytes: ArrayBuffer): string {
  return Array.from(new Uint8Array(bytes), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function hmac(secret: string, timestamp: string, body: string): Promise<string> {
  const key = await crypto.subtle.importKey("raw", new TextEncoder().encode(secret), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  return bytesToHex(await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(timestamp + "\n" + body)));
}

function hexEqual(a: string, b: string): boolean {
  if (!/^[0-9a-f]{64}$/i.test(a) || !/^[0-9a-f]{64}$/i.test(b)) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.toLowerCase().charCodeAt(i) ^ b.toLowerCase().charCodeAt(i);
  return diff === 0;
}

const DEFAULT_MODEL = "@cf/zai-org/glm-4.7-flash";
function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });
}

/**
 * Texte de la réponse, selon la forme réellement renvoyée par Workers AI.
 * Selon le modèle, la réponse arrive soit en `{ response: "..." }`, soit en
 * forme OpenAI `{ choices: [{ message: { content: "..." } }] }`. Renvoyer le
 * JSON brut à l'élève serait un défaut visible : on n'extrait que le texte, et
 * l'absence de texte est traitée comme une erreur fournisseur, pas comme une
 * réponse.
 */
function readReply(result: unknown): string | undefined {
  if (typeof result === "string") return result.trim() || undefined;
  const out = result as any;
  const direct = out?.response;
  if (typeof direct === "string" && direct.trim()) return direct.trim();
  const content = out?.choices?.[0]?.message?.content;
  if (typeof content === "string" && content.trim()) return content.trim();
  if (Array.isArray(content)) {
    const joined = content
      .map((part: any) => (typeof part === "string" ? part : typeof part?.text === "string" ? part.text : ""))
      .join("")
      .trim();
    if (joined) return joined;
  }
  return undefined;
}

/**
 * Consommation de jetons, quand le fournisseur la renvoie.
 * Workers AI n'a pas de forme unique selon les modèles : on accepte les deux
 * conventions connues et on omet l'attribut plutôt que d'inventer un chiffre.
 */
function readUsage(result: unknown) {
  const usage = (result as any)?.usage;
  if (!usage || typeof usage !== "object") return undefined;
  const pick = (...keys: string[]): number | undefined => {
    for (const key of keys) {
      const value = Number(usage[key]);
      if (Number.isFinite(value) && value >= 0) return value;
    }
    return undefined;
  };
  const prompt = pick("prompt_tokens", "input_tokens", "promptTokens", "inputTokens");
  const completion = pick("completion_tokens", "output_tokens", "completionTokens", "outputTokens");
  const total = pick("total_tokens", "totalTokens")
    ?? (prompt !== undefined || completion !== undefined ? (prompt ?? 0) + (completion ?? 0) : undefined);
  if (prompt === undefined && completion === undefined && total === undefined) return undefined;
  return { prompt, completion, total };
}

export default {
  async fetch(request: Request, env: Env, ctx?: TraceContext): Promise<Response> {
    if (request.method !== "POST") {
      return json({ code: "METHOD_NOT_ALLOWED", message: "POST requis" }, 405);
    }

    const timestamp = request.headers.get("x-jaspe-timestamp");
    const signature = request.headers.get("x-jaspe-signature");
    if (!env.JASPE_WORKER_HMAC_SECRET || !timestamp || !signature || !/^\d+$/.test(timestamp)) return json({ code: "AUTH_INVALID", message: "Signature requise" }, 401);
    const timestampSeconds = Number(timestamp);
    if (!Number.isSafeInteger(timestampSeconds) || Math.abs(Math.floor(Date.now() / 1000) - timestampSeconds) > REPLAY_WINDOW_SECONDS) return json({ code: "AUTH_REPLAY", message: "Requete expiree" }, 401);
    const rawBody = await request.text();
    let body: ChatRequest;
    try { body = JSON.parse(rawBody) as ChatRequest; } catch { return json({ code: "VALIDATION_INVALID", message: "Corps JSON invalide" }, 400); }
    if (JSON.stringify(body) !== rawBody) return json({ code: "AUTH_INVALID", message: "Corps non canonique" }, 401);
    const expected = await hmac(env.JASPE_WORKER_HMAC_SECRET, timestamp, rawBody);
    if (!hexEqual(expected, signature)) return json({ code: "AUTH_INVALID", message: "Signature invalide" }, 401);

    const message = (body.message || "").trim();
    if (!message || typeof body.session_key !== "string" || !body.session_key.trim()) {
      return json({ code: "VALIDATION_INVALID", message: "message requis" }, 400);
    }

    // Cerveau JASPE 2.5D — Cloudflare Workers AI avec AI Gateway.
    // Le binding env.AI est défini dans wrangler.toml (section [ai]).
    if (!env.AI) {
      return json({ code: "AI_NOT_CONFIGURED", message: "Workers AI non configuré" }, 503);
    }

    const model = env.JASPE_MODEL || DEFAULT_MODEL;
    const startedAt = Date.now();

    try {
      const systemPrompt = env.JASPE_SYSTEM_PROMPT
        || "Tu es JASPE, l'assistante de l'école SchoolSafe. Réponds avec bienveillance et pédagogie, en français.";

      const aiTarget = env.AI_GATEWAY || env.AI;

      // GLM-4.7-Flash est un modèle « reasoning » : sans cette option il produit
      // des jetons de raisonnement, ce qui fait varier la réponse de 18 à 60 s
      // et dépasse le délai de 40 s du client PHP. Le raisonnement n'apporte rien
      // à une réponse d'accueil scolaire ; on le désactive explicitement.
      const payload: Record<string, unknown> = {
        messages: [
          { role: "system", content: systemPrompt },
          { role: "user", content: message },
        ],
      };
      if (/glm/i.test(model)) payload.chat_template_kwargs = { enable_thinking: false };

      const result = await aiTarget.run(model, payload);

      const out: any = result as any;
      const text = readReply(result);
      if (!text) {
        traceJaspe(env, {
          sessionKey: body.session_key as string,
          model,
          startedAt,
          endedAt: Date.now(),
          ok: false,
          errorCode: "EMPTY_PROVIDER_REPLY",
        }, ctx);
        return json({ code: "PROVIDER_ERROR", message: "Reponse vide du fournisseur" }, 502);
      }

      // Métriques seulement : ni message, ni réponse, ni prompt système.
      traceJaspe(env, {
        sessionKey: body.session_key as string,
        model,
        responseModel: typeof out?.model === "string" ? out.model : undefined,
        startedAt,
        endedAt: Date.now(),
        ok: true,
        usage: readUsage(result),
      }, ctx);

      return json({ reply: text });
    } catch (err: any) {
      const msg = err?.message || String(err);

      traceJaspe(env, {
        sessionKey: body.session_key as string,
        model,
        startedAt,
        endedAt: Date.now(),
        ok: false,
        errorCode: "PROVIDER_ERROR",
      }, ctx);

      return json({ code: "PROVIDER_ERROR", message: msg }, 502);
    }
  },
};
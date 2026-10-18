/**
 * SchoolSafe JASPE — suivi Respan (OTLP/HTTP), optionnel.
 *
 * Envoie UNIQUEMENT des métriques d'exécution : horodatages (donc durée),
 * modèle, consommation de jetons quand le fournisseur la donne, issue
 * (succès / code d'erreur interne) et un identifiant de conversation haché.
 *
 * Ne partent JAMAIS : le message de l'élève ou du parent, la réponse de JASPE,
 * le prompt système, les noms, identifiants d'élève ou d'école. Respan reçoit
 * donc des métriques, pas des données scolaires. Le hachage du session_key
 * (déjà un HMAC côté PHP) empêche de relier un thread Respan aux identifiants
 * natifs.
 *
 * Contrat OTLP/HTTP (documenté par Respan) :
 *   POST {base}/api/v2/traces
 *   Authorization: Bearer <RESPAN_API_KEY>
 *   Content-Type: application/json
 * Champs requis par span : traceId (32 hex), spanId (16 hex), name,
 * startTimeUnixNano et endTimeUnixNano (chaînes). Les attributs sont optionnels.
 *
 * Le suivi est inerte sans RESPAN_API_KEY : JASPE répond exactement comme avant.
 * Un échec d'envoi ne change jamais la réponse et n'est jamais journalisé avec
 * de contenu utilisateur.
 */

export interface TraceEnv {
  RESPAN_API_KEY?: string;
  RESPAN_BASE_URL?: string;
}

export interface LlmTrace {
  /** session_key reçu du VPS (HMAC, jamais journalisé en clair). */
  sessionKey: string;
  model: string;
  responseModel?: string;
  startedAt: number;
  endedAt: number;
  ok: boolean;
  /** Code interne SchoolSafe (ex. PROVIDER_ERROR), jamais le message fournisseur. */
  errorCode?: string;
  usage?: {
    prompt?: number;
    completion?: number;
    total?: number;
  };
}

export interface TraceContext {
  waitUntil?: (promise: Promise<unknown>) => void;
}

const DEFAULT_BASE = "https://api.respan.ai";
const TRACE_TIMEOUT_MS = 1500;

function hex(bytes: number): string {
  const buffer = new Uint8Array(bytes);
  crypto.getRandomValues(buffer);
  return Array.from(buffer, (byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function sha256Hex(value: string): Promise<string> {
  const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(value));
  return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

function nanos(milliseconds: number): string {
  return String(Math.trunc(milliseconds)) + "000000";
}

type OtlpAttribute = { key: string; value: { stringValue?: string; intValue?: string } };

function intAttribute(key: string, value: number | undefined): OtlpAttribute | null {
  if (value === undefined || !Number.isFinite(value) || value < 0) return null;
  return { key, value: { intValue: String(Math.trunc(value)) } };
}

function attribute(key: string, value: string): OtlpAttribute {
  return { key, value: { stringValue: value } };
}

/** Le suivi est actif seulement si une clé est fournie (échec fermé, jamais bloquant). */
export function respanEnabled(env: TraceEnv | undefined): boolean {
  return Boolean(env && typeof env.RESPAN_API_KEY === "string" && env.RESPAN_API_KEY.trim().length > 0);
}

function baseUrl(env: TraceEnv): string {
  const raw = (env.RESPAN_BASE_URL || DEFAULT_BASE).trim().replace(/\/+$/, "");
  // La clé ne part que vers une origine HTTPS en clair : jamais en HTTP.
  if (!/^https:\/\/[a-z0-9.-]+\.[a-z]{2,}$/i.test(raw)) return DEFAULT_BASE;
  return raw;
}

/**
 * Construit la charge OTLP. Exportée pour être testable sans réseau.
 */
export async function buildTracePayload(trace: LlmTrace): Promise<Record<string, unknown>> {
  const thread = (await sha256Hex(trace.sessionKey)).slice(0, 32);

  const attributes: OtlpAttribute[] = [
    attribute("traceloop.span.kind", "chat"),
    attribute("llm.request.type", "chat"),
    attribute("gen_ai.system", "cloudflare-workers-ai"),
    attribute("gen_ai.request.model", trace.model),
    attribute("respan.threads.thread_identifier", thread),
    attribute("schoolsafe.outcome", trace.ok ? "ok" : "error"),
  ];

  if (trace.responseModel) attributes.push(attribute("gen_ai.response.model", trace.responseModel));
  if (!trace.ok && trace.errorCode) attributes.push(attribute("schoolsafe.error_code", trace.errorCode));

  const usage = trace.usage || {};
  const usageValues: Array<[string, number | undefined]> = [
    ["gen_ai.usage.prompt_tokens", usage.prompt],
    ["gen_ai.usage.completion_tokens", usage.completion],
    ["gen_ai.usage.total_tokens", usage.total],
  ];
  for (const [key, value] of usageValues) {
    const built = intAttribute(key, value);
    if (built) attributes.push(built);
  }

  return {
    resourceSpans: [{
      resource: {
        attributes: [
          attribute("service.name", "schoolsafe-jaspe"),
          attribute("service.version", "worker-jaspe"),
        ],
      },
      scopeSpans: [{
        scope: { name: "schoolsafe-jaspe" },
        spans: [{
          traceId: hex(16),
          spanId: hex(8),
          name: "jaspe.chat",
          kind: 3,
          startTimeUnixNano: nanos(trace.startedAt),
          endTimeUnixNano: nanos(trace.endedAt),
          attributes,
          status: trace.ok ? { code: 1 } : { code: 2, message: trace.errorCode || "ERROR" },
        }],
      }],
    }],
  };
}

/**
 * Envoi HTTP. Ne lève jamais : le suivi ne doit pas casser JASPE.
 * Renvoie le statut HTTP, ou 0 en cas d'échec réseau.
 */
export async function sendTrace(
  env: TraceEnv,
  payload: Record<string, unknown>,
  fetcher: typeof fetch = fetch,
): Promise<number> {
  if (!respanEnabled(env)) return 0;

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TRACE_TIMEOUT_MS);

  try {
    const response = await fetcher(baseUrl(env) + "/api/v2/traces", {
      method: "POST",
      headers: {
        Authorization: "Bearer " + (env.RESPAN_API_KEY as string).trim(),
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
    });
    return response.status;
  } catch {
    // Volontairement silencieux : aucune donnée utilisateur dans les journaux.
    return 0;
  } finally {
    clearTimeout(timer);
  }
}

/**
 * Trace un appel JASPE. Avec un ExecutionContext Cloudflare, l'envoi continue
 * après la réponse (waitUntil) : la latence perçue par l'utilisateur est
 * strictement identique à celle d'avant le suivi.
 */
export function traceJaspe(
  env: TraceEnv | undefined,
  trace: LlmTrace,
  ctx?: TraceContext,
  fetcher: typeof fetch = fetch,
): void {
  if (!respanEnabled(env)) return;

  const pending = buildTracePayload(trace)
    .then((payload) => sendTrace(env as TraceEnv, payload, fetcher))
    .catch(() => 0);

  if (ctx && typeof ctx.waitUntil === "function") {
    ctx.waitUntil(pending);
  } else {
    void pending;
  }
}

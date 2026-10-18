import assert from "node:assert/strict";
import { createHmac } from "node:crypto";
import { test } from "node:test";
import worker from "../src/index.ts";

/**
 * Suivi Respan : métriques seulement, jamais de donnée scolaire.
 * Chaque test remplace fetch le temps de l'appel puis le restaure.
 */

const secret = "test-worker-secret-32-bytes-minimum-xxxx";
const sessionKey = "d2f0c9a4b8e7f61029384756abcdef0123456789abcdef0123456789abcdef01";
const privateMessage = "Bonjour JASPE, je m'appelle Awa et je suis en 5e B";
const privateReply = "Bonjour Awa, ravie de te retrouver !";
const usage = { prompt_tokens: 12, completion_tokens: 34, total_tokens: 46 };

function signature(timestamp: string, content: string): string {
  return createHmac("sha256", secret).update(timestamp + "\n" + content).digest("hex");
}

function signedRequest(message = privateMessage, key = sessionKey): Request {
  const body = JSON.stringify({ message, session_key: key });
  const timestamp = String(Math.floor(Date.now() / 1000));
  return new Request("https://worker.test/", {
    method: "POST",
    headers: {
      "content-type": "application/json",
      "x-jaspe-timestamp": timestamp,
      "x-jaspe-signature": signature(timestamp, body),
    },
    body,
  });
}

function stubEnv(ai: any, extra: Record<string, unknown> = {}) {
  return { JASPE_WORKER_HMAC_SECRET: secret, AI: { run: ai }, ...extra } as any;
}

interface Captured { url: string; init: RequestInit }

function stubFetch(handler: (url: string, init: RequestInit) => Promise<Response> | Response) {
  const calls: Captured[] = [];
  const original = globalThis.fetch;
  globalThis.fetch = (async (input: any, init: any = {}) => {
    const url = typeof input === "string" ? input : input.url;
    calls.push({ url, init });
    return handler(url, init);
  }) as typeof fetch;
  return { calls, restore: () => { globalThis.fetch = original; } };
}

function collector() {
  const pending: Promise<unknown>[] = [];
  return { pending, waitUntil: (promise: Promise<unknown>) => { pending.push(promise); } };
}

function spanOf(call: Captured) {
  const payload = JSON.parse(String(call.init.body));
  return payload.resourceSpans[0].scopeSpans[0].spans[0];
}

function attributesOf(span: any): Map<string, string> {
  return new Map(span.attributes.map((entry: any) => [entry.key, entry.value.stringValue ?? entry.value.intValue]));
}

test("sans RESPAN_API_KEY : aucun envoi, réponse JASPE inchangée", async () => {
  const network = stubFetch(() => { throw new Error("aucun appel réseau attendu"); });
  try {
    const tl = collector();
    const res = await worker.fetch(signedRequest(), stubEnv(async () => ({ response: privateReply })), tl as any);
    await Promise.all(tl.pending);
    assert.equal(res.status, 200);
    assert.equal((await res.json()).reply, privateReply);
    assert.equal(network.calls.length, 0, "aucune requête ne part sans clé");
  } finally {
    network.restore();
  }
});

test("avec clé : OTLP conforme, durée et jetons, aucune donnée utilisateur", async () => {
  const network = stubFetch(() => new Response("{}", { status: 200 }));
  try {
    const tl = collector();
    const res = await worker.fetch(
      signedRequest(),
      stubEnv(async () => ({ response: privateReply, usage }), { RESPAN_API_KEY: "respan-test-key" }),
      tl as any,
    );
    await Promise.all(tl.pending);

    assert.equal(res.status, 200);
    assert.equal(network.calls.length, 1);
    const [call] = network.calls;
    assert.equal(call.url, "https://api.respan.ai/api/v2/traces");
    assert.equal((call.init.headers as any).Authorization, "Bearer respan-test-key");
    assert.equal((call.init.headers as any)["Content-Type"], "application/json");

    const span = spanOf(call);
    assert.match(span.traceId, /^[0-9a-f]{32}$/);
    assert.match(span.spanId, /^[0-9a-f]{16}$/);
    assert.match(span.startTimeUnixNano, /^\d{19}$/);
    assert.ok(BigInt(span.endTimeUnixNano) >= BigInt(span.startTimeUnixNano), "fin après début");
    assert.equal(span.status.code, 1);

    const attributes = attributesOf(span);
    assert.equal(attributes.get("gen_ai.request.model"), "@cf/zai-org/glm-4.7-flash");
    assert.equal(attributes.get("gen_ai.usage.prompt_tokens"), "12");
    assert.equal(attributes.get("gen_ai.usage.completion_tokens"), "34");
    assert.equal(attributes.get("gen_ai.usage.total_tokens"), "46");
    assert.equal(attributes.get("schoolsafe.outcome"), "ok");
    assert.match(String(attributes.get("respan.threads.thread_identifier")), /^[0-9a-f]{32}$/);
    assert.notEqual(attributes.get("respan.threads.thread_identifier"), sessionKey, "thread haché, pas la clé de session");

    const body = String(call.init.body);
    assert.ok(!body.includes("Awa"), "aucun prénom ne part");
    assert.ok(!body.includes(privateMessage), "aucun message ne part");
    assert.ok(!body.includes(privateReply), "aucune réponse de JASPE ne part");
  } finally {
    network.restore();
  }
});

test("erreur fournisseur : span en erreur, code interne seulement", async () => {
  const network = stubFetch(() => new Response("{}", { status: 200 }));
  try {
    const tl = collector();
    const res = await worker.fetch(
      signedRequest(),
      stubEnv(async () => { throw new Error("model exploded while answering Awa"); }, { RESPAN_API_KEY: "respan-test-key" }),
      tl as any,
    );
    await Promise.all(tl.pending);

    assert.equal(res.status, 502);
    assert.equal((await res.json()).code, "PROVIDER_ERROR");

    const span = spanOf(network.calls[0]);
    assert.equal(span.status.code, 2);
    const attributes = attributesOf(span);
    assert.equal(attributes.get("schoolsafe.outcome"), "error");
    assert.equal(attributes.get("schoolsafe.error_code"), "PROVIDER_ERROR");
    assert.ok(!String(network.calls[0].init.body).includes("exploded"), "le texte d'erreur du fournisseur ne part pas");
    assert.ok(!String(network.calls[0].init.body).includes("Awa"), "aucune donnée utilisateur dans l'erreur");
  } finally {
    network.restore();
  }
});

test("Respan indisponible : JASPE répond normalement", async () => {
  const network = stubFetch(() => { throw new Error("network down"); });
  try {
    const tl = collector();
    const res = await worker.fetch(
      signedRequest(),
      stubEnv(async () => ({ response: privateReply }), { RESPAN_API_KEY: "respan-test-key" }),
      tl as any,
    );
    await Promise.all(tl.pending);
    assert.equal(res.status, 200);
    assert.equal((await res.json()).reply, privateReply);
  } finally {
    network.restore();
  }
});

test("réponse Respan en erreur : aucun effet sur JASPE", async () => {
  const network = stubFetch(() => new Response("{\"error\":\"forbidden\"}", { status: 403 }));
  try {
    const tl = collector();
    const res = await worker.fetch(
      signedRequest(),
      stubEnv(async () => ({ response: privateReply }), { RESPAN_API_KEY: "respan-test-key" }),
      tl as any,
    );
    await Promise.all(tl.pending);
    assert.equal(res.status, 200);
    assert.equal((await res.json()).reply, privateReply);
  } finally {
    network.restore();
  }
});

/**
 * SchoolSafe JASPE 2.5D — Worker Cloudflare.
 *
 * Pont entre le VPS SchoolSafe et Cloudflare AI.
 * Le VPS appelle ce Worker via JASPE_WORKER_URL. Le Worker ne fait JAMAIS de SQL,
 * il appelle uniquement Cloudflare AI (Workers AI via binding).
 *
 * Sécurité (protocole SchoolSafeJaspeCloudflare) :
 *   - HMAC-SHA256 obligatoire : x-jaspe-timestamp (unix sec) +
 *     x-jaspe-signature = hex( HMAC( secret, timestamp + "\n" + corps brut ) )
 *   - JASPE_HMAC_SECRET défini via `wrangler secret` (jamais dans le code)
 *   - horloge : tolérance 300 s ; comparaison en temps constant
 *   - pas de CORS : appel serveur-à-serveur (PHP), aucun navigateur
 *
 * Bindings (wrangler.toml) :
 *   - AI (Workers AI) -> le « cerveau »
 * Le VPS n'a aucune clé AI ; tout se passe côté Worker.
 */

export interface Env {
	AI: any;
	AI_GATEWAY?: any;
	JASPE_MODEL?: string;
	JASPE_SYSTEM_PROMPT?: string;
	JASPE_HMAC_SECRET?: string;
}

interface ChatRequest {
	message?: string;
	session_key?: string;
	history?: Array<{ role?: string; content?: string }>;
}

const DEFAULT_MODEL = "@cf/zai-org/glm-4.7-flash";
const MAX_BODY_BYTES = 65536;
const MAX_MESSAGE_CHARS = 8000;
const MAX_REPLY_CHARS = 12000;
const CLOCK_TOLERANCE_SECONDS = 300;

function json(body: unknown, status = 200): Response {
	return new Response(JSON.stringify(body), {
		status,
		headers: { "Content-Type": "application/json; charset=utf-8" },
	});
}

function fail(status: number, code: string): Response {
	return json({ code: code, message: code }, status);
}

function toHex(buffer: ArrayBuffer): string {
	return [...new Uint8Array(buffer)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

async function verifySignature(
	rawBody: string,
	timestamp: string,
	signature: string,
	secret: string
): Promise<boolean> {
	if (!/^\d{1,12}$/.test(timestamp) || !/^[0-9a-f]{64}$/.test(signature)) return false;
	const now = Math.floor(Date.now() / 1000);
	if (Math.abs(now - parseInt(timestamp, 10)) > CLOCK_TOLERANCE_SECONDS) return false;
	const key = await crypto.subtle.importKey(
		"raw",
		new TextEncoder().encode(secret),
		{ name: "HMAC", hash: "SHA-256" },
		false,
		["sign"]
	);
	const expected = toHex(
		await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(timestamp + "\n" + rawBody))
	);
	if (expected.length !== signature.length) return false;
	let diff = 0;
	for (let i = 0; i < expected.length; i++) {
		diff |= expected.charCodeAt(i) ^ signature.charCodeAt(i);
	}
	return diff === 0;
}

export default {
	async fetch(request: Request, env: Env): Promise<Response> {
		if (request.method !== "POST") {
			return fail(405, "METHOD_NOT_ALLOWED");
		}
		const secret = env.JASPE_HMAC_SECRET || "";
		if (secret.length < 32) {
			return fail(503, "JASPE_NOT_CONFIGURED");
		}
		const rawBody = await request.text();
		if (rawBody.length === 0 || rawBody.length > MAX_BODY_BYTES) {
			return fail(413, "BODY_TOO_LARGE");
		}
		const ok = await verifySignature(
			rawBody,
			request.headers.get("x-jaspe-timestamp") || "",
			request.headers.get("x-jaspe-signature") || "",
			secret
		);
		if (!ok) {
			return fail(401, "INVALID_SIGNATURE");
		}
		let body: ChatRequest;
		try {
			body = JSON.parse(rawBody) as ChatRequest;
		} catch {
			return fail(400, "VALIDATION_INVALID");
		}
		const message = (body.message || "").trim();
		if (!message || message.length > MAX_MESSAGE_CHARS) {
			return fail(400, "VALIDATION_INVALID");
		}
		if (!env.AI) {
			return fail(503, "AI_NOT_CONFIGURED");
		}
		try {
			const model = env.JASPE_MODEL || DEFAULT_MODEL;
			const systemPrompt =
				env.JASPE_SYSTEM_PROMPT ||
				"Tu es JASPE, l'assistante de l'école SchoolSafe. Réponds avec bienveillance et pédagogie, en français.";
			const aiTarget = env.AI_GATEWAY || env.AI;
			// Memoire de conversation : derniers echanges envoyes par le VPS
			// (session PHP cote serveur, jamais stockee ici).
			const history = Array.isArray(body.history)
				? body.history
					.filter((h) => h && typeof h.content === "string" && (h.role === "user" || h.role === "assistant"))
					.slice(-8)
					.map((h) => ({ role: h.role as string, content: (h.content as string).slice(0, 1000) }))
				: [];

			const result = await aiTarget.run(model, {
				messages: [
					{ role: "system", content: systemPrompt },
					...history,
					{ role: "user", content: message },
				],
				max_tokens: 2000,
			});
			let text = "";
			const out: any = result as any;
			if (out?.response) text = String(out.response);
			else if (typeof result === "string") text = result;
			else if (out?.choices?.[0]?.message?.content) text = String(out.choices[0].message.content);
			else text = JSON.stringify(result);
			text = text.trim().slice(0, MAX_REPLY_CHARS);
			if (!text) return fail(502, "EMPTY_REPLY");
			return json({ reply: text });
		} catch (err: any) {
			const msg = err?.message || String(err);
			return json({ code: "PROVIDER_ERROR", message: msg }, 502);
		}
	},
};

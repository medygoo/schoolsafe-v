import { createHmac } from "node:crypto";
import { afterEach, describe, expect, it } from "vitest";
import type { BusinessPool } from "../src/db/pool.js";
import type { CardsNativeService } from "../src/cardsnative/service.js";
import type { DeviceHubService } from "../src/devicehub/service.js";
import type { FamilyNativeService } from "../src/familynative/service.js";
import { createScanControlService } from "../src/scancontrol/service.js";
import { signCardNumber } from "../src/security/service.js";

const SCHOOL = "33333333-0000-4000-8000-000000000001";
const STUDENT = "aaaaaaaa-0000-4000-8000-000000000001";
const CARD = "bbbbbbbb-0000-4000-8000-000000000001";
const LOCATION = "cccccccc-0000-4000-8000-000000000001";
const GUARDIAN = "dddddddd-0000-4000-8000-000000000001";
const SECRET = "scanner-test-secret";

const context = {
  userId: "55555555-0000-4000-8000-000000000001",
  profileId: "66666666-0000-4000-8000-000000000001",
  schoolId: SCHOOL,
  requestId: "req-scanner",
};

function pool(handler: (sql: string, params?: unknown[]) => unknown): { pool: BusinessPool; log: string[] } {
  const log: string[] = [];
  const client = {
    async query(sql: string, params?: unknown[]) {
      log.push(sql);
      return { rows: [{ result: handler(sql, params) }] };
    },
    release() {},
  };
  return { pool: { connect: async () => client } as unknown as BusinessPool, log };
}

function serviceFor(handler: (sql: string, params?: unknown[]) => unknown) {
  const seen = pool(handler);
  const service = createScanControlService(seen.pool, {
    deviceHub: {} as DeviceHubService,
    family: {} as FamilyNativeService,
    cards: {} as CardsNativeService,
  });
  return { service, log: seen.log };
}

function card(sql: string): Record<string, unknown> | boolean {
  if (sql.includes("scanner_card_lookup")) {
    return {
      found: true,
      signature_matches: true,
      status: "active",
      operational: true,
      scope_visible: true,
      student_id: STUDENT,
      first_name: "Amina",
      last_name: "Kalonji",
      class_name: "6e A",
      card_id: CARD,
      card_number: "SS-1",
    };
  }
  if (sql.includes("scanner_entry_record")) {
    return { result: "PRESENT", event_id: "eeeeeeee-0000-4000-8000-000000000001", occurred_at: "2026-10-10T08:00:00.000Z", student_name: "Amina Kalonji", class_name: "6e A" };
  }
  if (sql.includes("scan_control_presence_mark")) return { result: "PRESENT" };
  if (sql.includes("scan_control_presence")) return { currently_on_site: true, left_at: null };
  if (sql.includes("scanner_fee_authorize")) return { result: "ALLOWED" };
  if (sql.includes("scanner_fee_consult")) return { result: "FEE_STATUS", balance_status: "no_data", consultative: true };
  if (sql.includes("scanner_pickup_prepare")) return { result: "PREPARED", proof_token: "proof-token-value-32chars" };
  if (sql.includes("scanner_pickup_people")) {
    return { family: [{ guardian_id: GUARDIAN, name: "Marie Kalonji", type: "mere", pickup_ready: true, photo_accepted: true, photo_path: "/uploads/authorized-persons/a/b.jpg" }], accredited: [] };
  }
  if (sql.includes("scanner_pickup_confirm")) return { status: "confirmed", basis: "family", guardian_name: "Marie Kalonji" };
  return true;
}

function payload(cardNumber = "SS-1"): string {
  return `schoolsafe://card/${cardNumber}/${signCardNumber(cardNumber, SECRET)}`;
}

describe("scanner unique à quatre modes", () => {
  afterEach(() => {
    delete process.env.CARD_HMAC_SECRET;
  });

  it("signe les cartes remplaçées avec le même HMAC canonique que la vérification", () => {
    const canonical = signCardNumber("SS-1", SECRET);
    const hex = createHmac("sha256", SECRET).update("SS-1").digest("hex");
    expect(canonical).not.toBe(hex);
    expect(canonical).toHaveLength(32);
  });

  it("refuse un QR modifié avant toute lecture de carte", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service, log } = serviceFor(card);
    const result = await service.scan(context, { mode: "entry", qr_payload: payload().replace("SS-1", "SS-2"), location_id: LOCATION });
    expect(result).toMatchObject({ result: "INVALID", signature_valid: false });
    expect(log.some((sql) => sql.includes("scanner_card_lookup"))).toBe(false);
    expect(log.some((sql) => sql.includes("scanner_entry_record"))).toBe(false);
  });

  it("enregistre l'entrée sans moteur externe et sans lecture de frais", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service, log } = serviceFor(card);
    const result = await service.scan(context, { mode: "entry", qr_payload: payload(), location_id: LOCATION });
    expect(result).toMatchObject({ result: "PRESENT", decision: "allowed", student_name: "Amina Kalonji", class_name: "6e A" });
    expect(log.some((sql) => sql.includes("scanner_entry_record"))).toBe(true);
    expect(log.some((sql) => sql.includes("scan_control_presence_mark"))).toBe(false);
    expect(log.some((sql) => sql.includes("scanner_fee_consult"))).toBe(false);
  });

  it("prépare le retrait sans confirmer la remise", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service, log } = serviceFor(card);
    const result = await service.scan(context, { mode: "pickup", qr_payload: payload(), location_id: LOCATION });
    expect(result.result).toBe("PICKUP_CHOICE");
    expect(result.people).toMatchObject([{ id: GUARDIAN, photo_path: "/uploads/authorized-persons/a/b.jpg", pickup_ready: true }]);
    expect(log.some((sql) => sql.includes("scanner_pickup_confirm"))).toBe(false);
    expect(log.some((sql) => sql.includes("presence_mark"))).toBe(false);
  });

  it("exige la confirmation humaine avant la remise", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service, log } = serviceFor(card);
    const result = await service.scan(context, {
      mode: "pickup",
      qr_payload: payload(),
      location_id: LOCATION,
      authorized_person_id: GUARDIAN,
    });
    expect(result).toMatchObject({ result: "NEED_HUMAN_CONFIRMATION", proof_token: "proof-token-value-32chars" });
    expect(log.some((sql) => sql.includes("scanner_pickup_prepare"))).toBe(true);
    expect(log.some((sql) => sql.includes("scanner_pickup_confirm"))).toBe(false);
  });

  it("refuse une remise sans preuve de scan", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service, log } = serviceFor(card);
    const result = await service.scan(context, {
      mode: "pickup",
      qr_payload: payload(),
      location_id: LOCATION,
      authorized_person_id: GUARDIAN,
      human_confirmed: true,
    });
    expect(result).toEqual({ result: "PICKUP_PROOF_REQUIRED", decision: "denied", signature_valid: true });
    expect(log.some((sql) => sql.includes("scanner_pickup_confirm"))).toBe(false);
  });

  it("confirme la remise par le moteur existant, sans second événement de sortie", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service, log } = serviceFor(card);
    const result = await service.scan(context, {
      mode: "pickup",
      qr_payload: payload(),
      location_id: LOCATION,
      authorized_person_id: GUARDIAN,
      human_confirmed: true,
      proof_token: "proof-token-value-32chars",
    });
    expect(result).toMatchObject({ result: "CONFIRMED", decision: "allowed" });
    expect(log.filter((sql) => sql.includes("scanner_pickup_confirm"))).toHaveLength(1);
    expect(log.some((sql) => sql.includes("event_type"))).toBe(false);
  });

  it("lit les frais depuis le serveur et ignore un statut envoyé par le navigateur", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service, log } = serviceFor(card);
    const result = await service.scan(context, {
      mode: "fees",
      qr_payload: payload(),
      campaign_id: "ffffffff-0000-4000-8000-000000000001",
    });
    expect(result).toMatchObject({ result: "FEE_STATUS", balance_status: "no_data", consultative: true });
    expect(log.some((sql) => sql.includes("scanner_fee_consult"))).toBe(true);
    expect(log.some((sql) => sql.includes("fee_control_scan_create"))).toBe(false);
    expect(log.some((sql) => sql.includes("student_fee_status"))).toBe(false);
  });

  it("vérifie une carte sans présence, sortie, frais ni remise", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service, log } = serviceFor(card);
    const result = await service.scan(context, { mode: "verify", qr_payload: payload() });
    expect(result).toMatchObject({ result: "VALID", student_name: "Amina Kalonji", class_name: "6e A" });
    expect(log.some((sql) => sql.includes("scanner_entry_record"))).toBe(false);
    expect(log.some((sql) => sql.includes("scanner_pickup_confirm"))).toBe(false);
    expect(log.some((sql) => sql.includes("scanner_fee_consult"))).toBe(false);
    expect(log.some((sql) => sql.includes("presence_mark"))).toBe(false);
  });

  it("ne transforme pas un refus de portail en choix de remise", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service } = serviceFor((sql) => {
      if (sql.includes("scanner_pickup_people")) return { result: "PORTAL_DENIED" };
      return card(sql);
    });
    const result = await service.scan(context, { mode: "pickup", qr_payload: payload(), location_id: LOCATION });
    expect(result).toMatchObject({ result: "PORTAL_DENIED", decision: "denied" });
    expect(result.people).toBeUndefined();
  });

  it("refuse les anciens modes de sortie avant toute remise", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service, log } = serviceFor(card);
    await expect(service.scan(context, { mode: "gate_exit", qr_payload: payload(), location_id: LOCATION, human_confirmed: true, authorized_person_id: GUARDIAN })).rejects.toThrow(/accès officiel/);
    expect(log.some((sql) => sql.includes("scanner_pickup_confirm"))).toBe(false);
  });

  it("masque l'identité d'une carte inactive en contrôle des frais", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service } = serviceFor((sql) => {
      if (sql.includes("scanner_card_lookup")) {
        return { ...card(sql) as Record<string, unknown>, status: "lost" };
      }
      return card(sql);
    });
    const result = await service.scan(context, { mode: "fees", qr_payload: payload() });
    expect(result).toEqual({ result: "LOST", signature_valid: true, status: "lost" });
  });

  it("ne révèle pas le statut d'une carte inactive sans droit de frais", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const notes: unknown[][] = [];
    const { service } = serviceFor((sql, params) => {
      if (sql.includes("scanner_card_lookup")) return { ...card(sql) as Record<string, unknown>, status: "lost" };
      if (sql.includes("scanner_fee_authorize")) return { result: "NOT_DELEGATED" };
      if (sql.includes("scan_control_note")) notes.push(params ?? []);
      return card(sql);
    });
    const result = await service.scan(context, { mode: "fees", qr_payload: payload() });
    expect(result).toEqual({ result: "NOT_DELEGATED", signature_valid: true, decision: "denied" });
    expect(result).not.toHaveProperty("status");
    expect(result).not.toHaveProperty("student_id");
    expect(JSON.stringify(notes)).not.toContain(STUDENT);
  });

  it("masque l'identité quand le contrôle des frais est refusé", async () => {
    process.env.CARD_HMAC_SECRET = SECRET;
    const { service } = serviceFor((sql) => {
      if (sql.includes("scanner_fee_consult")) return { result: "NOT_DELEGATED" };
      return card(sql);
    });
    const result = await service.scan(context, { mode: "fees", qr_payload: payload() });
    expect(result).toEqual({ result: "NOT_DELEGATED", signature_valid: true, decision: "denied" });
  });
});

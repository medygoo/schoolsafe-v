import { bannedUntilIsActive, type AdminLookup } from "./admin.js";

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export type RelinkRequest = {
  schoolId: string;
  oldSubject: string;
  newSubject: string;
  confirm: string;
};

export function assertRelinkRequest(input: RelinkRequest): void {
  if (input.confirm !== "RELINK") {
    throw new Error("CONFIRMATION_REQUIRED");
  }
  if (!UUID.test(input.schoolId) || !UUID.test(input.oldSubject) || !UUID.test(input.newSubject) || input.oldSubject.toLowerCase() === input.newSubject.toLowerCase()) {
    throw new Error("EXACT_SUBJECT_REQUIRED");
  }
}

export function acceptReplacementSubject(lookup: AdminLookup, now = new Date()): boolean {
  return lookup.kind === "found" && !bannedUntilIsActive(lookup.bannedUntil, now);
}

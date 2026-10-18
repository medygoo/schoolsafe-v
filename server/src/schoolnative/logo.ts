import { randomUUID } from "node:crypto";
import { existsSync } from "node:fs";
import { mkdir, unlink, writeFile } from "node:fs/promises";
import path from "node:path";
import { SchoolSafeError } from "../http/errors.js";

const MAX_LOGO_BYTES = 2 * 1024 * 1024;
const SCHOOL_ID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const FILE_NAME = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(png|jpg|webp)$/i;

export type LogoImage = { ext: "png" | "jpg" | "webp"; mime: string };

export function detectLogoImage(bytes: Buffer): LogoImage | null {
  if (bytes.length >= 8 && bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47) {
    return { ext: "png", mime: "image/png" };
  }
  if (bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) {
    return { ext: "jpg", mime: "image/jpeg" };
  }
  if (bytes.length >= 12 && bytes.toString("ascii", 0, 4) === "RIFF" && bytes.toString("ascii", 8, 12) === "WEBP") {
    return { ext: "webp", mime: "image/webp" };
  }
  return null;
}

export function assertLogoBytes(bytes: Buffer, declaredType: string | undefined): LogoImage {
  if (bytes.length === 0) throw new SchoolSafeError(400, "FILE_MISSING", "Aucun fichier reçu", false);
  if (bytes.length > MAX_LOGO_BYTES) throw new SchoolSafeError(400, "FILE_TOO_LARGE", "Fichier trop volumineux (max 2 Mo)", false);
  const detected = detectLogoImage(bytes);
  const declared = declaredType && declaredType !== "application/octet-stream" ? declaredType : detected?.mime;
  if (!detected || declared !== detected.mime) {
    throw new SchoolSafeError(400, "FILE_INVALID", "Format non supporté (PNG, JPG, WEBP)", false);
  }
  return detected;
}

function schoolDirectory(root: string, schoolId: string): string {
  if (!SCHOOL_ID.test(schoolId)) throw new SchoolSafeError(400, "VALIDATION_INVALID", "École invalide", false);
  const base = path.resolve(root);
  const target = path.resolve(base, schoolId.toLowerCase());
  if (!target.startsWith(base + path.sep)) throw new SchoolSafeError(400, "VALIDATION_INVALID", "École invalide", false);
  return target;
}

export async function writeSchoolLogo(
  root: string,
  schoolId: string,
  bytes: Buffer,
  image: LogoImage,
): Promise<{ publicPath: string; absolutePath: string }> {
  const directory = schoolDirectory(root, schoolId);
  await mkdir(directory, { recursive: true });
  const filename = `${randomUUID()}.${image.ext}`;
  const absolutePath = path.join(directory, filename);
  await writeFile(absolutePath, bytes, { flag: "wx" });
  return { publicPath: `/native/school/logo-file/${filename}`, absolutePath };
}

export async function removeNewLogo(absolutePath: string): Promise<void> {
  await unlink(absolutePath).catch(() => undefined);
}

export function resolveSchoolLogo(root: string, schoolId: string, filename: string): string | null {
  if (!FILE_NAME.test(filename)) return null;
  const directory = schoolDirectory(root, schoolId);
  const absolute = path.resolve(directory, filename);
  if (!absolute.startsWith(directory + path.sep) || !existsSync(absolute)) return null;
  return absolute;
}

export function logoMime(filename: string): string {
  if (filename.endsWith(".png")) return "image/png";
  if (filename.endsWith(".jpg")) return "image/jpeg";
  return "image/webp";
}

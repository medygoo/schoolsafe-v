import path from "node:path";
import { writeSchoolLogo, type LogoImage } from "../schoolnative/logo.js";

export async function writeAuthorizedPortrait(
  root: string,
  schoolId: string,
  bytes: Buffer,
  image: LogoImage,
): Promise<{ photoPath: string; absolutePath: string }> {
  const stored = await writeSchoolLogo(root, schoolId, bytes, image);
  const filename = path.basename(stored.absolutePath);
  return {
    absolutePath: stored.absolutePath,
    photoPath: `/uploads/authorized-persons/${schoolId.toLowerCase()}/${filename}`,
  };
}

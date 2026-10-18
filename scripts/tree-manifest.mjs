#!/usr/bin/env node
/**
 * Tree manifest — generate or verify a deterministic sha256 manifest of the workspace.
 *
 * Why this exists: TREE.sha256 (1 017 entries) contains **no** entry under
 * app/modules/codex-ui-v1/**, so it could never prove anything about the runtime.
 * This tool declares its scope explicitly instead of inheriting an implicit one.
 *
 * Usage (from the workspace root):
 *   node scripts/tree-manifest.mjs --write  ../TREE_V2.sha256
 *   node scripts/tree-manifest.mjs --verify ../TREE_V2.sha256
 *
 * Format, identical to TREE.sha256: <relative/path>\t<sha256>\t<size>\n, sorted by path.
 *
 * Scope: every regular file under the workspace root, except:
 *   - directories .git/, node_modules/, vendor/ and .codex-local-reference-v1/ (local secrets)
 *   - files ending in .log, and any TREE*.sha256 manifest (no self-reference)
 *   - .env-family files (even as hashes: a manifest must never describe a secret)
 *     except the published templates .env.example / .env.sample / .env.template
 *   - symbolic links (counted and reported, never followed: no cycles, no escapes)
 *
 * Exit codes: 0 = conforming, 1 = drift, 2 = usage or I/O error.
 */
import { createHash } from "node:crypto";
import { createReadStream } from "node:fs";
import { mkdir, readdir, readFile, stat, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const EXCLUDED_DIRECTORIES = new Set([".git", "node_modules", "vendor", ".codex-local-reference-v1"]);
const EXCLUDED_EXTENSIONS = new Set([".log"]);
const SECRET_TEMPLATES = new Set([".env.example", ".env.sample", ".env.template"]);
const MANIFEST_PATTERN = /^TREE.*\.sha256$/;
const HASH_CONCURRENCY = 8;
const MAX_REPORTED = 20;

function usage(message) {
  if (message) console.error(`tree-manifest: ${message}`);
  console.error("usage: node scripts/tree-manifest.mjs --write <manifest> | --verify <manifest>");
  process.exit(2);
}

function parseArguments(argv) {
  const [mode, manifest] = argv;
  if (!["--write", "--verify"].includes(mode) || !manifest) usage();
  const root = repoRoot;
  return { mode, manifest: path.resolve(root, manifest), root };
}

function relative(root, absolute) {
  return path.relative(root, absolute).split(path.sep).join("/");
}

function isExcludedFile(name) {
  if (MANIFEST_PATTERN.test(name) || EXCLUDED_EXTENSIONS.has(path.extname(name))) return true;
  if (SECRET_TEMPLATES.has(name)) return false;
  // .env, .env.local, prod.env… : a hash is still a description of a secret.
  return name === ".env" || name.startsWith(".env.") || name.endsWith(".env");
}

/** Walk the tree once; returns { files, symlinks, skipped } with workspace-relative paths. */
async function walk(root) {
  const files = [];
  const symlinks = [];
  const stack = [root];

  while (stack.length) {
    const directory = stack.pop();
    const entries = await readdir(directory, { withFileTypes: true });

    for (const entry of entries) {
      const absolute = path.join(directory, entry.name);

      if (entry.isSymbolicLink()) {
        symlinks.push(relative(root, absolute));
        continue;
      }

      if (entry.isDirectory()) {
        if (!EXCLUDED_DIRECTORIES.has(entry.name)) stack.push(absolute);
        continue;
      }

      if (!entry.isFile() || isExcludedFile(entry.name)) continue;
      files.push(absolute);
    }
  }

  files.sort((a, b) => (relative(root, a) < relative(root, b) ? -1 : 1));
  return { files, symlinks };
}

async function hashFile(absolute) {
  const hash = createHash("sha256");
  for await (const chunk of createReadStream(absolute)) hash.update(chunk);
  return hash.digest("hex");
}

/** Hash files with a small pool so a 10k-file tree stays usable. */
async function hashAll(root, files) {
  const hashes = new Map();
  let cursor = 0;

  async function worker() {
    while (cursor < files.length) {
      const absolute = files[cursor++];
      const { size } = await stat(absolute);
      hashes.set(relative(root, absolute), { hash: await hashFile(absolute), size });
    }
  }

  await Promise.all(Array.from({ length: Math.min(HASH_CONCURRENCY, files.length) }, worker));
  return hashes;
}

async function write(root, manifest) {
  const { files, symlinks } = await walk(root);
  const hashes = await hashAll(root, files);
  const lines = [];
  let bytes = 0;

  for (const file of files) {
    const key = relative(root, file);
    const { hash, size } = hashes.get(key);
    bytes += size;
    lines.push(`${key}\t${hash}\t${size}`);
  }

  await mkdir(path.dirname(manifest), { recursive: true });
  await writeFile(manifest, `${lines.join("\n")}\n`, "utf8");

  console.log(
    `${path.basename(manifest)}: ${lines.length} files, ${(bytes / 1048576).toFixed(1)} MiB, ` +
      `${symlinks.length} symlink(s) skipped`,
  );
  if (symlinks.length) console.log(`  skipped: ${symlinks.slice(0, MAX_REPORTED).join(", ")}`);
  return 0;
}

function parseManifest(text) {
  const entries = new Map();
  for (const line of text.split("\n")) {
    if (!line) continue;
    const [file, hash, size] = line.split("\t");
    if (!file || !/^[0-9a-f]{64}$/.test(hash ?? "")) usage(`malformed manifest line: ${line}`);
    entries.set(file, { hash, size: Number(size) });
  }
  return entries;
}

async function verify(root, manifest) {
  const expected = parseManifest(await readFile(manifest, "utf8"));
  const { files, symlinks } = await walk(root);
  const present = new Map();

  for (const file of files) {
    const key = relative(root, file);
    present.set(key, await stat(file));
  }

  const changed = [];
  const missing = [];
  let unchanged = 0;

  for (const [file, { hash, size }] of expected) {
    const absolute = path.join(root, file);

    if (!present.has(file)) {
      missing.push(file);
      continue;
    }

    const actual = await hashFile(absolute);
    if (actual !== hash) changed.push(`${file} (sha256 ${actual.slice(0, 12)}…, expected ${hash.slice(0, 12)}…)`);
    else if (present.get(file).size !== size) changed.push(`${file} (size ${present.get(file).size} ≠ ${size})`);
    else unchanged++;
  }

  const extra = [...present.keys()].filter((file) => !expected.has(file));

  console.log(
    `TREE check: ${unchanged} OK · ${changed.length} modified · ${missing.length} missing · ` +
      `${extra.length} untracked · ${symlinks.length} symlink(s) skipped · ${expected.size} expected`,
  );
  for (const line of changed.slice(0, MAX_REPORTED)) console.log(`  MODIFIED ${line}`);
  for (const line of missing.slice(0, MAX_REPORTED)) console.log(`  MISSING  ${line}`);
  for (const line of extra.slice(0, MAX_REPORTED)) console.log(`  UNTRACKED ${line}`);

  return changed.length + missing.length + extra.length === 0 ? 0 : 1;
}

const { mode, manifest, root } = parseArguments(process.argv.slice(2));
const manifestExists = await stat(manifest).then(
  () => true,
  () => false,
);

if (mode === "--write") process.exitCode = await write(root, manifest);
else if (!manifestExists) usage(`${path.basename(manifest)} not found`);
else process.exitCode = await verify(root, manifest);

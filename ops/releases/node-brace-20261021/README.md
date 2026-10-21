# Targeted Node dependency release — 21 October 2026

Authorized scope: brace-expansion 5.0.9 -> 5.0.11, without upgrading the whole production application. No PHP/Gitleaks changes, schema migration, real-account test or message dispatch.

## Build contract

This directory's Dockerfile is a recipe, not a complete context. The operator must prepare a private, dedicated context as follows:

1. Verify the current Node image ID is exactly `sha256:33a181d8c820b84e23d53ab79058a6911d5311c7cbfa7a8601bb7667618a9a1f`; retain it and the original container.
2. Verify live configuration and writable data locations; back up configuration, database/roles and uploads privately. Verify database restoration in an isolated, network-disabled PostgreSQL instance.
3. Download `https://registry.npmjs.org/brace-expansion/-/brace-expansion-5.0.11.tgz` and validate its exact npm SRI:
   `sha512-awigjhi6cLTh90bdw6+QJ9CtmJmyYhEIi70iCbc8Rozn04Fw9FeQIBjv/E22FFGuCGx1bLJyUfB64x/szUSXUg==`.
4. Extract only safe regular `package/` members; no links, absolute paths or parent traversal. Verify version, dependencies and engines against the existing package before replacement.
5. Derive `package-lock.json` from the running image, changing only `version`, `resolved`, `integrity` under `packages["node_modules/brace-expansion"]`. Do not replace it with a different application's full lockfile.
6. Tag the verified base image as `schoolsafe-app:brace-base-33a181d8` and build with `--pull=false --network=none`. Record the resulting image ID and confirm its base layers match exactly.
7. Run `scripts/security-runtime-brace-check.cjs before` in the original image and `after` in the delivery image, both in disposable containers with network disabled and bounded resources. The before test must reproduce the three upstream stack-overflow defects. The after test must pass normal expansion and the real Fastify static-plugin integration checks.
8. Preserve the original container's runtime configuration. Stop only Node, archive/copy its upload directory with ownership preserved, start the new image and compare health/readiness with baseline. Keep an automatic rollback path. No simultaneous production Node instances.

The targeted image intentionally keeps the compiled application, Fastify 5.12.4, all other packages and startup behavior from the existing image. Root/workspace and standalone source lockfiles are separately reconciled for future source builds; the standalone lockfile is not injected into the runtime image.

## Source qualification already performed

- Fresh workspace and standalone dependency installs.
- TypeScript check/build passed.
- Full server suite: 688/688 after correction, including a repeat from a clean workspace install.
- `node --test scripts/security-dependency-contract.test.cjs`: five checks passed; the three upstream parser regressions failed against the original installed version before replacement.

These are local qualification results. Deployment was subsequently authorized and verified on 21 October 2026: see [the delivery report](../../../docs/qualification/NODE_BRACE_RELEASE_20261021.md) for exact image identity, independent production checks, rollback conditions and remaining RED security findings.

## Rollback

Do not restore an old database merely to roll back this dependency-only release. Stop/retain the replacement container, restore the prior container and preserve any uploads that may have changed since the switch. Re-evaluate the procedure after subsequent releases rather than blindly running an old rollback script.

No production credential, dump, upload, runtime configuration file or private inspection output belongs in Git.

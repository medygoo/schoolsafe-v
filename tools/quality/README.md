# SchoolSafe — quality tools, not a production certification

## Placement

- Local + GitHub Actions: Playwright, Gitleaks, Semgrep, axe-core, Trivy, Lighthouse CI.
- Isolated fictional targets only: ZAP. The supplied contract checks the passive missing-`nosniff` header rule, not an active penetration test.
- Separate local service: Bugsink. No production PHP/Node SDK, DSN, event forwarding or real user data has been configured by this tooling change.

The workflow `SchoolSafe quality tools` has read-only repository permissions. It does not deploy, access the VPS, use application credentials, send messages or connect to a production database. It runs on manual dispatch, pull requests to main/production, and selected changes on main. It is separate from the existing workflow named `CI`; it cannot trigger the existing production deployment chain.

## Qualified versions

| Tool | Version | Positive/negative proof |
|---|---|---|
| Playwright | 1.62.1 | Working and deliberately broken buttons at desktop/mobile widths |
| Gitleaks | 8.30.1 | Clean file / synthetic never-issued PAT, exit 0/1, redaction verified |
| Semgrep | 1.182.0 | Safe PHP / `eval` fixture, exit 0/1, curated explicit rules |
| axe-core integration | @axe-core/playwright 4.14.0 | Accessible fixture / nameless button detected |
| Trivy | 0.76.0 | Non-root/root Dockerfiles and vulnerable lodash lockfile; no vulnerable package executed |
| Lighthouse CI | 0.15.1 | Accessibility score assertion accepts clean fixture and rejects defective fixture |
| OWASP ZAP | 2.17.0, official bare image | `nosniff` header present/absent distinguished on internal Docker network |
| Bugsink | 2.6.1 | Two sanitized synthetic events persisted, missing/invalid ingestion key rejected |

Docker images are digest-pinned in the scripts/workflow. Gitleaks/Trivy archives are checked against SHA-256 constants derived from their official release checksums. This is checksum integrity, not a claim of independent publisher signature verification. Browser dependencies have exact versions and an npm lockfile; application dependencies are unchanged.

## Local or CI usage

Requirements: Node 22+, Python 3, Docker Linux engine, x64 for the scanner installer. Use a local Docker context; never redirect these scripts to production.

```sh
npm ci --prefix tools/quality --ignore-scripts --no-audit --no-fund
node tools/quality/node_modules/playwright-core/cli.js install --with-deps chromium
npm test --prefix tools/quality
python3 tools/quality/install-scanners.py

docker pull semgrep/semgrep@sha256:4a34048c04fef049f71665047f074cffb00cc3d89482f965a5cd151d589237af
python3 tools/quality/security-verify.py selftest
python3 tools/quality/security-verify.py source

docker pull ghcr.io/zaproxy/zaproxy@sha256:0cbc40e0fa1562d5170a18a247eca579c4d155ad869f8944c3b8526d4a58c64c
docker pull bugsink/bugsink@sha256:c5e3657451f4ba1ddb145a5118a4347314206cc5ba98f40e5f268c6c0975963e
python3 tools/quality/zap-verify.py
```

On Windows use `python` if appropriate. `CHROME_PATH` may select the already qualified local Chromium executable; in CI the pinned Playwright browser is installed. No global npm package or global PATH change is required. Lighthouse uses `--no-sandbox` only in the disposable GitHub Actions runner against its anonymous loopback fixture, to accommodate the runner's Chromium sandbox restrictions. This flag is not applied to local runs or production browsers.

## What the checks actually prove

Browser checks currently qualify the tools against anonymous, fictional fixtures. They are not SchoolSafe business E2E tests. In particular, the PHP runtime/image needed for LOT 1-A is not reproducibly built by this workflow; that broader build qualification remains LOT 2 work.

The `source` security lane does examine the real repository:
- Gitleaks: Git history reachable by the scanner's default Git scan, with explicit default rules and 100% finding redaction.
- Semgrep: server/src, PHP ProgramFunctions and ecole-profils.php, using the three committed dynamic-evaluation rules. This is a deliberately limited rule set, not a comprehensive code audit.
- Trivy: high/critical dependency and configuration findings in the working tree, excluding node_modules, Git metadata and this tool package. It does not certify the actual production image.

`selftest` expects a failing scanner exit on deliberately defective fixtures and verifies the corresponding finding type. `source` treats findings as failures. Do not turn findings into a passing result using blanket exclusions or `continue-on-error`.

Initial local repository scan: 10 generic-api-key history findings, 12 high/critical dependency findings (11 HIGH, 1 CRITICAL), and 4 HIGH configuration findings. Semgrep's limited source scan passed. Findings require triage: these counts are not proof of actual exposed credentials or exploitable production vulnerabilities. No application dependency, credential or business code was automatically changed.

The workflow does not alter branch protection. A published status check is not automatically a mandatory merge gate; repository policy must be decided separately.

## Bugsink boundary

The operator's local service is `schoolsafe-tooling-bugsink`, accessible only at `http://127.0.0.1:8017`, with a dedicated volume, generated credentials, telemetry disabled and a dummy email backend. Private credentials live outside Git under the local tooling directory with restrictive Windows ACLs. Docker administrators can inspect runtime secrets; never paste container environment dumps.

`telemetry-filter.cjs` constructs a new event from an allowlist and discards raw messages, users, requests, cookies, authorization headers, URLs, QR values, stack locals, breadcrumbs and arbitrary tags. Its `php` and `node` tags were tested through the collector; this does **not** mean that an actual PHP SDK or a Node application exception hook is installed. `telemetry-verify.cjs` tests this privacy contract without any network call.

Production monitoring still requires separate application integration, staging verification, a protected durable collector, operational retention/backup decisions and deployment review. Nothing in this workflow sends errors from SchoolSafe production to the local collector.

## Reports, safety and rollback

Local generated reports/binaries/caches/fixtures are ignored by Git. Detailed security reports are not uploaded by Actions because this repository is public. They can contain sensitive surrounding context even when a matched secret is redacted. Review detailed reports privately.

ZAP's API uses a generated key and an allowlist limited to the fixture client/loopback names. It has no host port and runs on an internal network; its target has fictional content only. The sample's other passive alerts are retained in the summary, not hidden: passing the contract means the specific header defect was distinguished, not that the whole fixture is alert-free. Temporary ZAP containers/network are removed after each run.

Rollback: revert only the tooling commit(s) or disable this workflow; leave unrelated application changes intact. Remove only named tooling resources when authorized. For Bugsink, stop/remove `schoolsafe-tooling-bugsink`; delete its dedicated volume only after confirming it still contains no data worth retaining. Never run a global Docker prune, npm cache purge, repository reset or session revocation as a tooling rollback.

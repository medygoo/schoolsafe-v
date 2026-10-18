const fs = require("fs");
const path = require("path");
const { chromium } = require("playwright");

const baseUrl = (process.env.SCHOOLSAFE_URL || "http://127.0.0.1:8080").replace(/\/$/, "");
const harnessUrl = `${baseUrl}/modules/SchoolSafe_Jaspe/jaspe2d/geste-harness.html`;
const outputDir = path.join(__dirname, "qa-output");

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });
  const browser = await chromium.launch({
    args: [
      "--disable-background-timer-throttling",
      "--disable-renderer-backgrounding",
      "--disable-backgrounding-occluded-windows",
    ],
  });
  let report;
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await page.goto(harnessUrl, { waitUntil: "load", timeout: 30000 });
    try {
      await page.waitForFunction(() => window.__harness && window.__harness.status !== "running", null, {
        timeout: 1200000,
        polling: 200,
      });
    } catch (error) {
      const partial = await page.evaluate(() => ({ ...window.__harness, status: "timeout" })).catch(() => null);
      fs.writeFileSync(path.join(outputDir, "jaspe-gestures.json"), JSON.stringify(partial, null, 1));
      console.log("progression:", await page.evaluate(() => document.getElementById("report").textContent).catch(() => ""));
      throw error;
    }
    report = await page.evaluate(() => window.__harness);
    await page.screenshot({ path: path.join(outputDir, "jaspe-gestures.png") }).catch(() => {});
  } finally {
    await browser.close();
  }
  // Les identifiants produits par les règles PHP doivent tous exister côté moteur.
  const rulesFile = path.join(__dirname, "modules", "codex-ui-v1", "extensions-test-runtime", "ProgramFunctions", "SchoolSafeJaspeMotion.fnc.php");
  const vocabulary = new Set(report.vocabulary || []);
  const ids = new Set();
  for (const match of fs.readFileSync(rulesFile, "utf8").matchAll(/return \[(?:'([a-zA-Z][\w]*)'|null), ?(?:'([a-zA-Z][\w]*)'|null)\]/g)) {
    if (match[1]) ids.add(match[1]);
    if (match[2]) ids.add(match[2]);
  }
  const unknown = [...ids].filter(id => !vocabulary.has(id));
  console.log(`regles PHP: ${ids.size} identifiants, inconnus du moteur: ${unknown.join(",") || "aucun"}`);
  if (unknown.length) report.failures = [...(report.failures || []), ...unknown.map(id => "regle PHP inconnue: " + id)];

  const intents = report.intents || [];
  const actions = report.actions || [];
  const failures = report.failures || [];
  const played = item => item.ms >= 0; // 0 = geste joué, raté par le sondage mais vu dans le journal
  console.log(`gestes: ${intents.filter(played).length}/${intents.length} intentions jouees`);
  console.log(`actions: ${actions.filter(played).length}/${actions.length} actions jouees`);
  console.log(`duree de montage: ${intents[0] && intents[0].mountMs} ms par moteur`);
  console.log(`moteur: ${report.engine} | indisponibles: ${(report.unavailable || []).join(",") || "aucune"}`);
  for (const failure of failures) console.log(`ECHEC ${failure}`);
  fs.writeFileSync(path.join(outputDir, "jaspe-gestures.json"), JSON.stringify(report, null, 1));
  console.log(`statut: ${report.status}${report.error ? " " + report.error : ""}`);
  process.exitCode = (report.status === "passed" && !(report.failures || []).length) ? 0 : 1;
})().catch(error => {
  console.error(String(error && error.stack || error));
  process.exitCode = 1;
});

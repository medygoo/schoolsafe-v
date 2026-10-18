const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const root = path.resolve(__dirname, "..");
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), "utf8");
const app = read("app/app.js");
const html = read("app/index.html");
const cards = read("app/modules/cards/cards-module.js");

function sliceBetween(source, startMarker, endMarker) {
  const start = source.indexOf(startMarker);
  assert.ok(start >= 0, "Marqueur absent : " + startMarker);
  const end = source.indexOf(endMarker, start + startMarker.length);
  assert.ok(end > start, "Fin absente après " + startMarker);
  return source.slice(start, end);
}

const bottomNav = sliceBetween(html, 'id="workspaceBottomNav"', "</nav>");
const bottomButtons = [...bottomNav.matchAll(/data-bottom-nav=["']([^"']+)["']/g)].map((match) => match[1]);
assert.deepEqual(bottomButtons, ["dashboard", "jaspe", "menu"], "La barre mobile doit rester Dashboard, JASPE, Menu");
assert.match(app, /SchoolSafeJaspeDashboard\.open\(btn\)/, "JASPE doit ouvrir le panneau existant");
assert.match(app, /Rattrapage pédagogique/, "Le rattrapage pédagogique doit rester dans le catalogue");
assert.match(app, /remediation/, "Le rattrapage pédagogique doit viser remediation");
assert.match(app, /Versement de rattrapage/, "Le versement de rattrapage doit rester dans Finance");
assert.match(read("app/modules/finance/finance-module.js"), /remediation-finance/, "Finance doit conserver l'onglet remediation-finance");
assert.match(app, /Scanner un QR/, "Le scanner QR doit rester dans Sécurité");
assert.match(app, /SchoolSafeSecurityModule/, "Le scanner doit appeler le module Sécurité existant");

const primaryAssignment = app.slice(app.indexOf("workspaceNav.innerHTML"), app.indexOf("desktopModuleGrid"));
assert.doesNotMatch(primaryAssignment, /data-context-action/, "Le menu principal ne doit pas redevenir un accordéon");

assert.match(app, /function schoolAccessNotice/, "schoolAccessNotice de Rosario doit être présent");
assert.match(app, /SCHOOL_SUSPENDED/, "Une école suspendue doit être reconnue");
assert.match(app, /SCHOOL_REVOKED/, "Une école révoquée doit être reconnue");
assert.match(app, /schoolAccessNotice\(error\)/, "L'échec de connexion doit utiliser l'avis Rosario");

assert.match(app, /Cartes scolaires/, "École doit exposer le groupe Cartes scolaires");
assert.match(app, /Studio des cartes/, "L'entrée Studio des cartes doit exister");
assert.match(app, /SchoolSafeCards\.open\(/, "openActionByBranch doit ouvrir SchoolSafeCards.open");
assert.match(cards, /open:\s*openCardsStudio/, "SchoolSafeCards.open doit être public");
assert.match(cards, /close:\s*closeCardsStudio/, "SchoolSafeCards.close doit être public");
assert.doesNotMatch(cards, /getElementById\(["']navCards["']\)/, "Le studio ne doit plus dépendre d'un bouton #navCards");

assert.match(app, /Centre documentaire/, "Administration doit exposer le Centre documentaire");
assert.match(app, /openDocumentCenter\(\)/, "Le Centre documentaire doit appeler le moteur existant");

console.log("SchoolSafe Rosario visual bindings: PASS");

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const root = path.resolve(__dirname, "..");
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), "utf8");
const html = read("app/index.html");
const app = read("app/app.js");
const dashboard = read("app/styles/dashboard.css");

function sliceBetween(source, startMarker, endMarker) {
  const start = source.indexOf(startMarker);
  assert.ok(start >= 0, `Marqueur absent : ${startMarker}`);
  const end = source.indexOf(endMarker, start + startMarker.length);
  assert.ok(end > start, `Fin absente après ${startMarker}`);
  return source.slice(start, end);
}

assert.match(html, /id=["']workspaceContextNav["']/, "#workspaceContextNav est requis");
assert.match(html, /id=["']workspaceContextNavTitle["']/, "#workspaceContextNavTitle est requis");
assert.match(html, /id=["']workspaceContextNavList["']/, "#workspaceContextNavList est requis");
assert.match(html, /id=["']workspaceContextNav["'][^>]*aria-label=["']Navigation du module["']/, "Le volet contextuel doit exposer aria-label");

const primaryNav = sliceBetween(html, 'id="workspaceNav"', "</nav>");
assert.doesNotMatch(primaryNav, /workspaceContextNav/, "#workspaceContextNav ne doit pas être enfant de #workspaceNav");
const sidebar = sliceBetween(html, 'id="workspaceSidebar"', "</aside>");
assert.doesNotMatch(sidebar, /workspaceContextNav/, "Le sous-menu doit être un panneau séparé du menu principal");

const bottomNav = sliceBetween(html, 'id="workspaceBottomNav"', "</nav>");
const bottomButtons = [...bottomNav.matchAll(/data-bottom-nav=["']([^"']+)["']/g)].map((match) => match[1]);
assert.deepEqual(bottomButtons, ["dashboard", "jaspe", "menu"], "La barre mobile doit rester Dashboard, JASPE, Menu");
assert.match(bottomNav, /adossee-detouree\.png/, "L'image JASPE existante doit rester au centre");
assert.doesNotMatch(bottomNav, /data-bottom-nav=["']action["']/i, "Aucun bouton Action ne doit être ajouté");
assert.match(bottomNav, /data-bottom-nav=["']menu["'][^>]*aria-expanded=["'](?:true|false)["']/, "Le bouton Menu doit exposer aria-expanded");

assert.match(html, /localStorage\.getItem\(["']ss-theme["']\)/, "Le stockage ss-theme doit rester compatible");
assert.match(html, />Blanc</, "Le thème clair doit être présenté comme Blanc");
assert.match(html, />Bleu</, "Le thème sombre doit être présenté comme Bleu");
assert.doesNotMatch(html, /Mode sombre/, "Le libellé Mode sombre doit être remplacé");
assert.match(html, /data-theme-choice=["']light["']/, "Le choix Blanc doit rester sélectionnable");
assert.match(html, /data-theme-choice=["']dark["']/, "Le choix Bleu doit rester sélectionnable");

assert.match(app, /function openWorkspaceContextNav\(/, "openWorkspaceContextNav est requis");
assert.match(app, /function closeWorkspaceContextNav\(/, "closeWorkspaceContextNav est requis");
assert.match(app, /function renderWorkspaceContextNav\(/, "renderWorkspaceContextNav est requis");
assert.match(app, /visibleBranches/, "Le sous-menu doit partir des branches déjà visibles");
assert.match(app, /openActionByBranch\(branchKey,\s*actionName\)/, "Une sous-fonction doit appeler openActionByBranch");
assert.match(app, /aria-current/, "L'état actif ne doit pas dépendre uniquement de la couleur");
assert.doesNotMatch(
  sliceBetween(app, "workspaceNav.innerHTML", "desktopModuleGrid"),
  /data-context-action/,
  "Les actions contextuelles ne doivent pas être injectées sous les modules principaux"
);

assert.match(dashboard, /\.workspace-screen \.workspace-sidebar\s*\{[^}]*width:\s*260px/, "Le menu principal desktop reste à 260px");
assert.match(dashboard, /#workspaceContextNav\s*\{[^}]*width:\s*220px/, "Le sous-menu desktop fait 220px");
assert.match(dashboard, /#workspaceContextNav\s*\{[^}]*overflow-y:\s*auto/, "Le sous-menu doit défiler verticalement");
assert.match(dashboard, /#workspaceContextNav\s*\{[^}]*left:\s*260px/, "Le sous-menu desktop s'aligne à droite du menu principal");
assert.match(dashboard, /context-nav-open \.workspace-main\s*\{[^}]*margin-left:\s*480px/, "Le contenu desktop laisse 480px quand le sous-menu est ouvert");

assert.match(dashboard, /@media\s*\(min-width:\s*769px\)\s*and\s*\(max-width:\s*1024px\)[\s\S]*width:\s*64px/, "Le menu principal tablette fait 64px");
assert.match(dashboard, /@media\s*\(min-width:\s*769px\)\s*and\s*\(max-width:\s*1024px\)[\s\S]*#workspaceContextNav[\s\S]*width:\s*200px/, "Le sous-menu tablette fait 200px");
assert.match(dashboard, /@media\s*\(min-width:\s*769px\)\s*and\s*\(max-width:\s*1024px\)[\s\S]*margin-left:\s*264px/, "Le contenu tablette laisse 264px quand le sous-menu est ouvert");
assert.match(dashboard, /@media\s*\(min-width:\s*769px\)\s*and\s*\(max-width:\s*1024px\)[\s\S]*margin-left:\s*64px/, "Le contenu tablette revient à 64px quand le sous-menu est fermé");

assert.match(dashboard, /@media\s*\(max-width:\s*768px\)[\s\S]*#workspaceContextNav[\s\S]*width:\s*min\(210px,\s*60vw\)/, "Le sous-menu mobile ne dépasse pas min(210px, 60vw)");
assert.match(dashboard, /@media\s*\(max-width:\s*768px\)[\s\S]*#workspaceContextNav[\s\S]*overflow-y:\s*auto/, "Le sous-menu mobile défile");
assert.match(dashboard, /@media\s*\(max-width:\s*768px\)[\s\S]*#workspaceContextNav[\s\S]*bottom:\s*\d+px/, "Le sous-menu mobile ne couvre pas la bottom navigation");

assert.match(dashboard, /#workspaceContextNavList button\.active\s*\{[^}]*(?:font-weight|border)/, "La sous-fonction active est identifiable autrement que par la couleur");
assert.match(dashboard, /\.workspace-nav button\.active\s*\{[^}]*(?:font-weight|border|box-shadow)/, "Le grand module actif est identifiable autrement que par la couleur");

console.log("SchoolSafe two-level navigation contract: PASS");

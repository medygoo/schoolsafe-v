const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const scope = { window: {} };
for (const file of ['app/modules/codex-ui-v1/catalog.js', 'app/modules/core/visual-wiring.js']) {
  vm.runInNewContext(fs.readFileSync(path.join(root, file), 'utf8'), scope, { timeout: 1000 });
}
const catalog = scope.window.SchoolSafeCodexCatalog;
const map = scope.window.SchoolSafeVisualWiring.MAP;
const bindings = JSON.parse(fs.readFileSync(path.join(root,
  'app/modules/codex-ui-v1/rosario-view/screen-bindings.json'), 'utf8'));
const sourceLines = fs.readFileSync(path.join(root, 'app/modules/core/visual-wiring.js'), 'utf8').split(/\r?\n/);
const rows = catalog.actions.map(action => {
  const key = action.legacy ? action.legacy.branch + '|' + action.legacy.action : null;
  const target = key ? map[key] || null : null;
  const line = key ? sourceLines.findIndex(value => value.includes(JSON.stringify(key) + ':')) + 1 : 0;
  const binding = bindings.find(row => row.id === action.id);
  return {
    id: action.id, branch: action.branch, label: action.label,
    nativeRoute: binding.nativeRoute,
    existingVisualRegistryTarget: target,
    registryEvidence: line ? 'app/modules/core/visual-wiring.js:' + line : null,
    registryIsEffectiveHandlerVerified: false,
    warning: target === 'gap'
      ? 'Base registry declares a gap; higher-priority visual-output handlers may override it.'
      : target ? 'Registry entry exists; not proof of API or workflow execution.'
      : 'No entry in this base registry; other handlers must be examined.',
    workflowTested: false
  };
});
const summary = {
  destinations: rows.length,
  phpNavigationBindings: rows.filter(row => row.nativeRoute).length,
  baseVisualRegistryEntries: rows.filter(row => row.existingVisualRegistryTarget).length,
  baseRegistryGapDeclarations: rows.filter(row => row.existingVisualRegistryTarget === 'gap').length,
  bothPhpAndBaseVisualRegistry: rows.filter(row => row.nativeRoute && row.existingVisualRegistryTarget).length
};
fs.writeFileSync(path.join(__dirname, 'CONNECTION_PROVENANCE_V1.json'),
  JSON.stringify({ summary, rows }, null, 2) + '\n');
console.log(JSON.stringify(summary));

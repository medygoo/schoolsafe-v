const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const view = path.join(root, 'app/modules/codex-ui-v1/rosario-view');
const scope = { window: {} };
vm.runInNewContext(fs.readFileSync(path.join(root, 'app/modules/codex-ui-v1/catalog.js'), 'utf8'), scope);
const catalog = scope.window.SchoolSafeCodexCatalog;
const routes = JSON.parse(fs.readFileSync(path.join(view, 'screen-routes.json'), 'utf8'));
const source = 'C:/Users/PC/Documents/rosario/modules';
for (const [id, route] of Object.entries(routes)) {
  if (!catalog.actions.some(action => action.id === id)) throw new Error('Destination inconnue: ' + id);
  if (!/^[A-Za-z_]+\/[A-Za-z_]+\.php$/.test(route) || !fs.existsSync(path.join(source, route))) {
    throw new Error('Route native absente ou invalide: ' + route);
  }
}
const rows = catalog.actions.map(action => ({
  id: action.id, branch: action.branch, label: action.label,
  nativeRoute: routes[action.id] || null,
  status: routes[action.id] ? 'NATIVE_SCREEN_BINDING_PERMISSION_REQUIRED' : action.id === 'rosario-016' ? 'STUDIO_CARD_PRESERVED' : 'NO_CONFIRMED_PHP_SCREEN_BINDING',
  visual: 'EXISTING_CATALOG_DESTINATION_NOT_PROOF_OF_COMPLETE_SCREEN',
  workflowTested: false
}));
fs.writeFileSync(path.join(view, 'screen-bindings.json'), JSON.stringify(rows, null, 2) + '\n');
fs.writeFileSync(path.join(__dirname, 'ROSARIO_SCREEN_BINDINGS_V1.json'), JSON.stringify(rows, null, 2) + '\n');
console.log(JSON.stringify({ destinations: rows.length, mapped: Object.keys(routes).length,
  preserved: rows.filter(row => row.status === 'STUDIO_CARD_PRESERVED').length,
  unconfirmed: rows.filter(row => row.status === 'NO_CONFIRMED_PHP_SCREEN_BINDING').length }));

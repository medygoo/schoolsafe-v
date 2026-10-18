const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, 'school-native-presentation.js'), 'utf8');
function run(url, options = {}) {
    const classes = new Set();
    const links = [];
    let onLoad;
    const nativeDocument = {
        body: {},
        documentElement: {classList: {add: value => classes.add(value)}},
        head: {appendChild: link => links.push(link)},
        getElementById: id => links.find(link => link.id === id),
        createElement: tag => ({tag}),
    };
    const frame = {contentDocument: nativeDocument,
        contentWindow: {location: {href: url}},
        addEventListener: (name, callback) => {assert.equal(name, 'load'); onLoad = callback;}};
    if (options.denied) Object.defineProperty(frame, 'contentWindow', {get() {throw new Error('cross origin');}});
    vm.runInNewContext(source, {URL, Set, window: {location: {
        origin: 'http://localhost:18082', href: 'http://localhost:18082/schoolsafe-view/school-live.php'}},
        document: {querySelector: () => options.absent ? null : frame}});
    if (onLoad) onLoad();
    return {classes, links};
}
for (const module of ['Schools', 'Calendar', 'MarkingPeriods', 'Periods', 'GradeLevels',
    'PortalNotes', 'PortalPolls', 'SchoolFields', 'Configuration']) {
    const result = run(`http://localhost:18082/Modules.php?modname=School_Setup/${module}.php`);
    assert(result.classes.has('ss-native-school'));
    assert.equal(result.links.length, 1, 'stylesheet must be idempotent');
    assert.equal(result.links[0].href, 'http://localhost:18082/schoolsafe-view/school-native-presentation.css?v=3');
}
for (const url of ['http://other.test/Modules.php?modname=School_Setup/Schools.php',
    'http://localhost:18082/index.php',
    'http://localhost:18082/Modules.php?modname=Users/User.php',
    'http://localhost:18082/Modules.php?modname=School_Setup/CopySchool.php',
    'http://localhost:18082/Modules.php?modname=School_Setup/../Schools.php']) {
    assert.equal(run(url).links.length, 0);
}
assert.equal(run('http://localhost:18082/', {absent: true}).links.length, 0);
assert.equal(run('http://localhost:18082/', {denied: true}).links.length, 0);
console.log('16 presentation cases passed; no form or network API exposed to adapter.');

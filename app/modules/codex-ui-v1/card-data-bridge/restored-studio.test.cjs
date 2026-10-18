const test = require('node:test');
const assert = require('node:assert/strict');
const { toRestoredStudioData } = require('./restored-studio-data.cjs');
const { installOfficialQrGuard } = require('./restored-studio-qr-guard.cjs');
function fixture() {
  return { issuanceAllowed: false, targetReference: { studentId: 's', schoolId: 'school' }, missing: [],
    view: { student: { id: 's', last_name: 'Nom', first_name: 'Prenom', mat: 'SCH0023-000002', qr: '', parent_name: 'Parent' },
      classData: { id: 'c', name: 'Classe', cycle: 'primaire' }, teacher: { name: 'Enseignant' }, year: '2026-2027', school: { name: 'Ecole' } } };
}
test('maps verified presentation without granting pickup rights', () => {
  const data = toRestoredStudioData(fixture());
  assert.equal(data.students[0].cid, 'c');
  assert.equal(data.students[0].name, 'Nom Prenom');
  assert.equal(data.students[0].mat, 'SCH0023-000002');
  assert.equal(data.students[0].official_qr, '');
  assert.deepEqual(data.aps, []);
  assert.equal(data.users.length, 2);
});
test('missing class or mismatched student is refused', () => {
  const a = fixture(); a.missing.push('class.mapping');
  assert.throws(() => toRestoredStudioData(a));
  const b = fixture(); b.view.student.id = 'other';
  assert.throws(() => toRestoredStudioData(b));
});
test('both generators preserve artwork but remove demonstration QR', () => {
  const studio = { ssBuildBadge: () => ({ recto: 'r', verso: 'v', qr: 'schoolsafe://student/demo' }),
    ssBuildCarte: () => ({ recto: 'r', verso: 'v', qr: 'schoolsafe://student/demo' }) };
  installOfficialQrGuard(studio);
  for (const name of ['ssBuildBadge', 'ssBuildCarte']) {
    assert.deepEqual(studio[name]({}), { recto: 'r', verso: 'v', qr: '' });
    assert.equal(studio[name]({ official_qr: 'schoolsafe://student/demo' }).qr, '');
    assert.equal(studio[name]({ official_qr: 'schoolsafe://card/number/signature' }).qr, 'schoolsafe://card/number/signature');
  }
});

test('QR rendering ignores empty and demonstration payloads', () => {
  const calls = [];
  const studio = { ssBuildBadge: () => ({}), ssBuildCarte: () => ({}),
    ssGenQR: (...args) => calls.push(args) };
  installOfficialQrGuard(studio);
  studio.ssGenQR('qr', '', 76);
  studio.ssGenQR('qr', 'schoolsafe://student/demo', 76);
  assert.equal(calls.length, 0);
  studio.ssGenQR('qr', 'schoolsafe://card/number/signature', 76);
  assert.equal(calls.length, 1);
});

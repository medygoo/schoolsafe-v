// SchoolSafe V2 — Module de production de cartes élèves dans le workspace
import { renderCardPreview, captureCardPng, ssClassType } from './card-renderer.js';

const state = {
  classes: [],
  students: [],
  selectedClass: null,
  selectedStudentIds: new Set(),
  academicYears: [],
  assignments: [],
  schoolSettings: null,
  cardMotto: '',
  authorizedByStudent: {},
  openGeneration: 0,
  classEpoch: 0,
  productionGeneration: 0,
  productionRunning: false,
  productionMode: '',
  productionReport: null,
  producedStudentIds: new Set(),
  apiBase: window.schoolSafeApiBase || window.SCHOOLSAFE_API_BASE || 'http://127.0.0.1:8787'
};

const SCHOOL_PRODUCTION_BLOCK = "Production de toute l'école bloquée : contrat serveur manquant. POST /native/cards/batches ne regroupe que des demandes déjà soumises. Aucune opération serveur ne pagine les élèves autorisés, n'émet les credentials manquants, ne rend les visuels et ne reprend le lot sans doublon.";

function adaptRosarioCard(input) {
  if (!window.SchoolSafeCardAdapter) throw new Error("Adaptateur de carte indisponible");
  return window.SchoolSafeCardAdapter.adaptRosarioCard(input);
}

function $(id) { return document.getElementById(id); }

function setStatus(msg, type = 'ok') {
  const el = $('cardsStatus');
  if (!el) return;
  if (msg && typeof msg === 'object') {
    el.innerHTML = window.ssState(msg);
    el.style.color = '';
  } else {
    el.textContent = msg;
    el.style.color = type === 'error' ? '#c22f2f' : type === 'warning' ? '#b8860b' : '#08825a';
  }
}

function nativeApi(path, opts) {
  var options = opts || {};
  var url = state.apiBase + '/native' + path;
  var fetchOpts = {
    method: options.method || 'GET',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    credentials: 'include',
  };
  if (options.body) fetchOpts.body = JSON.stringify(options.body);
  return fetch(url, fetchOpts).then(function (res) {
    if (!res.ok) return res.json().then(function (d) { throw new Error(d && d.message ? d.message : 'Erreur ' + res.status); });
    return res.json();
  });
}

function getToken() {
  try {
    const session = JSON.parse(sessionStorage.getItem('schoolsafe-v2-session') || 'null');
    return session?.token || null;
  } catch { return null; }
}

function getSupabaseClient() {
  if (!window.SchoolSafeSupabaseSDK?.createClient) return null;
  const config = window.schoolSafeBackendConfig;
  if (!config?.supabase_url || !config?.supabase_anon_key) return null;
  return window.SchoolSafeSupabaseSDK.createClient(config.supabase_url, config.supabase_anon_key, {
    auth: { autoRefreshToken: true, persistSession: false }
  });
}

async function loadClasses() {
  try {
    var res = await nativeApi('/pedagogy/classes');
    var classesData = (res && res.data) || [];

    // Récupérer les configs de design
    var configRes = await nativeApi('/cards/class-visual');
    var configMap = {};
    if (configRes && configRes.data) {
      configRes.data.forEach(function (c) { configMap[c.id] = c; });
    }

    state.classes = classesData.map(function (c) {
      var cfg = configMap[c.id] || {};
      return {
        id: c.id,
        name: c.name,
        cycle_key: c.cycle_key,
        option: c.option,
        academic_year_id: c.academic_year_id || cfg.academic_year_id || null,
        card_color: cfg.card_color,
        card_color_soft: cfg.card_color_soft,
        card_color_dark: cfg.card_color_dark,
        card_pat: cfg.card_pat,
        card_family: cfg.card_family,
        card_variant: cfg.card_variant,
        card_pat_style: cfg.card_pat_style
      };
    });

    var select = $('cardsClassSelect');
    select.innerHTML = '<option value="">Choisir une classe</option>';
    state.classes.forEach(function (c) {
      var opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.name;
      select.appendChild(opt);
    });
  } catch (e) {
    setStatus({ type: 'error', title: 'Erreur de chargement', message: 'Erreur chargement classes : ' + e.message, size: 'inline' });
  }
}

function mapListedStudent(s) {
  return {
    id: s.id,
    matricule: s.matricule,
    first_name: s.first_name,
    middle_name: s.middle_name,
    last_name: s.last_name,
    date_of_birth: s.date_of_birth,
    photo_path: s.photo_path,
    school_id: s.school_id,
    lifecycle_status: s.lifecycle_status,
    class_id: s.class_id,
    class_name: s.class_name,
    card_print_count: s.card_print_count || 0,
    students_guardians: s.students_guardians || []
  };
}

async function readStudentPages(classId, isStale) {
  const pageSize = 200;
  let offset = 0;
  let total = null;
  const rows = [];
  const seen = new Set();
  while (offset < 100000) {
    let path = '/students?status=active&limit=' + pageSize + '&offset=' + offset;
    if (classId) path += '&class_id=' + encodeURIComponent(classId);
    const res = await nativeApi(path);
    if (isStale()) return null;
    const payload = (res && res.data) || {};
    const page = Array.isArray(payload) ? payload : (payload.rows || []);
    if (total == null && payload && typeof payload.total === 'number') total = payload.total;
    page.forEach(function (row) {
      if (!row || seen.has(row.id)) return;
      seen.add(row.id);
      rows.push(row);
    });
    offset += page.length || pageSize;
    if (!page.length) break;
    if (total == null) break;
    if (offset >= total || rows.length >= total) break;
  }
  return rows;
}

async function loadStudents(classId) {
  const generation = state.openGeneration;
  const epoch = state.classEpoch;
  try {
    const studentsData = await readStudentPages(classId, function () {
      return generation !== state.openGeneration || epoch !== state.classEpoch;
    });
    if (!studentsData) return false;
    state.students = studentsData.map(mapListedStudent);
    state.selectedStudentIds.clear();
    renderStudentList();
    $('cardsRenderBtn').disabled = state.students.length === 0;
    $('cardsRequestPrintBtn').disabled = true;
    $('cardsPreview').innerHTML = window.ssState({ type: 'empty', title: 'Aucun aperçu', message: 'Sélectionnez un ou plusieurs élèves.', size: 'compact' });
    return true;
  } catch (e) {
    setStatus({ type: 'error', title: 'Erreur de chargement', message: 'Erreur chargement élèves : ' + e.message, size: 'inline' });
  }
}

function authHeaders() {
  var headers = { Accept: 'application/json' };
  var token = getToken();
  if (token) headers.Authorization = 'Bearer ' + token;
  return headers;
}

async function bearerJson(path) {
  var res = await fetch(state.apiBase + path, { credentials: 'include', headers: authHeaders() });
  if (!res.ok) throw new Error('Erreur ' + res.status);
  return res.json();
}

async function loadCardMotto() {
  try {
    var res = await nativeApi('/cards/school-motto');
    var data = (res && res.data) || {};
    state.cardMotto = data.motto || '';
  } catch (e) {
    state.cardMotto = '';
  }
}

async function loadSchoolSettings() {
  try {
    var res = await nativeApi('/cards/school-identity');
    var settings = (res && res.data) || null;
    if (!settings || !settings.identity) throw new Error('contrat incomplet');
    state.schoolSettings = settings;
    window.SCHOOL_LOGO = settings.brand && settings.brand.logo_path ? settings.brand.logo_path : '';
  } catch (e) {
    state.schoolSettings = null;
    window.SCHOOL_LOGO = '';
    setStatus({ type: 'warning', title: 'École', message: 'Identité école indisponible : ' + e.message, size: 'inline' });
  }
}

async function loadAssignments() {
  try {
    var res = await nativeApi('/pedagogy/teacher-assignments');
    var data = res && res.data;
    state.assignments = Array.isArray(data) ? data : ((data && data.rows) || []);
  } catch (e) {
    state.assignments = [];
    setStatus({ type: 'warning', title: 'Titulaire', message: 'Affectations indisponibles : ' + e.message, size: 'inline' });
  }
}

function classTutor(classId) {
  return state.assignments.find(function (item) {
    return item.class_id === classId && item.is_tutor === true;
  }) || null;
}

function isCardInfoComplete(s) {
  return Boolean(s && s.id && s.first_name && s.last_name);
}

function renderStudentList() {
  const list = $('cardsStudentList');
  list.innerHTML = '';
  if (state.students.length === 0) {
    list.innerHTML = window.ssState({ type: 'empty', title: 'Aucun élève', message: 'Aucun élève dans cette classe.', size: 'compact' });
    return;
  }
  state.students.forEach(s => {
    const complete = isCardInfoComplete(s);
    const label = document.createElement('label');
    label.title = complete ? 'Informations complètes' : 'Informations incomplètes';
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.value = s.id;
    checkbox.checked = state.selectedStudentIds.has(s.id);
    checkbox.disabled = !complete;
    checkbox.addEventListener('change', () => {
      if (checkbox.checked) state.selectedStudentIds.add(s.id);
      else state.selectedStudentIds.delete(s.id);
      updateSelectionState();
    });
    const nameSpan = document.createElement('span');
    nameSpan.textContent = `${s.last_name} ${s.first_name}`;
    const meta = document.createElement('span');
    meta.className = 'student-meta';
    meta.textContent = complete ? (s.card_print_count > 0 ? `v${s.card_print_count + 1}` : 'prêt') : 'incomplet';
    if (!complete) label.style.opacity = '0.6';
    label.appendChild(checkbox);
    label.appendChild(nameSpan);
    label.appendChild(meta);
    list.appendChild(label);
  });
  updateSelectionState();
}

function updateSelectionState() {
  const count = state.selectedStudentIds.size;
  const btn = $('cardsRequestPrintBtn');
  const renderBtn = $('cardsRenderBtn');
  btn.disabled = count === 0;
  renderBtn.disabled = count === 0;
  $('cardsSelectAll').checked = count > 0 && count === state.students.filter(s => isCardInfoComplete(s)).length;
  setStatus(count === 0 ? { type: 'empty', title: 'Aucune sélection', message: 'Sélectionnez un ou plusieurs élèves.', size: 'inline' } : `${count} élève(s) sélectionné(s).`);
}

function listPayload(value) {
  if (Array.isArray(value)) return value;
  if (value && Array.isArray(value.rows)) return value.rows;
  return [];
}

function paintAuthorizedSelect() {
  var select = $('cardsAuthorizedSelect');
  if (!select) return;
  select.disabled = true;
  select.innerHTML = '<option value="">Non vérifiée — absente de la carte officielle</option>';
  select.value = '';
}

async function cardViewFor(student) {
  await loadSchoolSettings();
  var recordRes = await nativeApi('/students/' + encodeURIComponent(student.id) + '/record');
  var familyRes = await nativeApi('/students/' + encodeURIComponent(student.id) + '/family');
  var phoneRes = await nativeApi('/cards/students/' + encodeURIComponent(student.id) + '/guardian-phones');
  var credentialRes = await nativeApi('/cards/students/' + encodeURIComponent(student.id) + '/credential');
  var credential = (credentialRes && credentialRes.data) || {};
  var yearLabel = '';
  if (state.selectedClass && state.selectedClass.id) {
    try {
      var yearRes = await nativeApi('/cards/students/' + encodeURIComponent(student.id) + '/academic-year?class_id=' + encodeURIComponent(state.selectedClass.id));
      yearLabel = (yearRes && yearRes.data && yearRes.data.label) || '';
    } catch (e) {
      yearLabel = '';
    }
  }
  var select = $('cardsAuthorizedSelect');
  var selectedId = state.authorizedByStudent[student.id] || (select ? select.value : '');
  var view = adaptRosarioCard({
    record: (recordRes && recordRes.data) || {},
    family: listPayload(familyRes && familyRes.data),
    guardianPhones: listPayload(phoneRes && phoneRes.data),
    schoolSettings: state.schoolSettings,
    cardMotto: state.cardMotto,
    academicYears: state.academicYears,
    academicYearLabel: yearLabel,
    assignments: state.assignments,
    classRow: state.selectedClass,
    cardConfig: state.selectedClass,
    credential: credential,
    selectedAuthorizedId: selectedId
  });
  paintAuthorizedSelect(view.authorizedPeople, student.id);
  return view;
}

async function renderPreviewForStudent(student) {
  if (!state.selectedClass || !student) return null;
  var view = await cardViewFor(student);
  var container = $('cardsPreview');
  if (!view.student.qr) {
    container.innerHTML = window.ssState({ type: 'empty', title: 'Carte non encore émise', message: 'La consultation ne crée pas de carte.', size: 'compact' });
    return view;
  }
  renderCardPreview(container, view.student, view.classData, view.teacher, view.year, view.school, view.school.logo, $('cardsPatStyle').value);
  return view;
}

async function renderPreview() {
  if (!state.selectedClass) return;
  const selected = state.students.filter(s => state.selectedStudentIds.has(s.id));
  if (selected.length === 0) {
    setStatus({ type: 'error', title: 'Sélection requise', message: 'Sélectionnez au moins un élève.', size: 'inline' });
    return;
  }
  var view = await renderPreviewForStudent(selected[0]);
  if (view && !view.student.qr) {
    setStatus({ type: 'empty', title: 'Carte non encore émise', message: 'La consultation ne crée pas de carte.', size: 'inline' });
    return view;
  }
  var tutor = classTutor(state.selectedClass.id);
  setStatus({ type: 'success', title: 'Aperçu prêt', message: `Aperçu de ${selected[0].first_name} ${selected[0].last_name}. Titulaire : ${tutor && tutor.teacher_name ? tutor.teacher_name : 'non affecté'}. ${selected.length > 1 ? `+ ${selected.length - 1} autre(s) sélectionné(s).` : ''}`, size: 'inline' });
  return view;
}

async function generateCardPayload(student) {
  var view = await cardViewFor(student);
  if (!view.student.qr) throw new Error('Carte non encore émise');
  const { type } = ssClassType(view.classData);
  const container = $('cardsPreview');
  renderCardPreview(container, view.student, view.classData, view.teacher, view.year, view.school, view.school.logo, $('cardsPatStyle').value);
  await new Promise(r => setTimeout(r, 80));
  const wrapSelector = type === 'badge' ? '.ss-badge-wrap' : '.ss-carte-wrap';
  const frontDataUrl = await captureCardPng(container, wrapSelector + ' .art:first-child');
  const backDataUrl = await captureCardPng(container, wrapSelector + ' .art:last-child');
  return {
    student_id: student.id,
    format: type,
    front_image_base64: frontDataUrl,
    back_image_base64: backDataUrl,
    academic_year_id: view.academicYearId,
    metadata: {
      class_name: state.selectedClass.name,
      requested_at: new Date().toISOString()
    }
  };
}

function fileToBase64(file) {
  return new Promise(function (resolve, reject) {
    var reader = new FileReader();
    reader.onload = function () {
      var value = String(reader.result || '');
      resolve(value.slice(value.indexOf(',') + 1));
    };
    reader.onerror = function () { reject(reader.error || new Error('Lecture de la photo impossible')); };
    reader.readAsDataURL(file);
  });
}

async function uploadOfficialPhoto(student, file) {
  var contentType = file.type === 'image/png' ? 'image/png' : file.type === 'image/webp' ? 'image/webp' : 'image/jpeg';
  var image = await fileToBase64(file);
  await nativeApi('/students/' + encodeURIComponent(student.id) + '/photo', {
    method: 'POST',
    body: { content_type: contentType, image_base64: image }
  });
}

async function requestPrintBatch() {
  const confirm = $('cardsProductionConfirm');
  if (confirm && !confirm.checked) {
    setStatus({ type: 'error', title: 'Confirmation requise', message: 'Cochez la confirmation avant d\'envoyer la sélection.', size: 'inline' });
    return;
  }
  const token = getToken();
  if (!token) {
    setStatus({ type: 'error', title: 'Connexion requise', message: 'Vous devez être connecté.', size: 'inline' });
    return;
  }
  const selected = state.students.filter(s => state.selectedStudentIds.has(s.id));
  if (selected.length === 0) {
    setStatus({ type: 'error', title: 'Sélection requise', message: 'Sélectionnez au moins un élève.', size: 'inline' });
    return;
  }

  setStatus({ type: 'loading', title: 'Génération en cours', message: `Génération de ${selected.length} carte(s)…`, size: 'inline' });
  const payloads = [];
  for (let i = 0; i < selected.length; i++) {
    try {
      const view = await cardViewFor(selected[i]);
      const gaps = officialGaps(view);
      if (gaps.length) {
        setStatus({ type: 'error', title: 'Dossier incomplet', message: studentLabel(selected[i]) + ' : ' + gaps.join(', ') + '. Impression officielle bloquée.', size: 'inline' });
        return;
      }
      const payload = await generateCardPayload(selected[i]);
      payloads.push(payload);
      setStatus({ type: 'loading', title: 'Génération en cours', message: `Génération ${i + 1}/${selected.length}…`, size: 'inline' });
    } catch (e) {
      setStatus({ type: 'error', title: 'Erreur de génération', message: `Erreur génération pour ${selected[i].first_name} ${selected[i].last_name} : ${e.message}`, size: 'inline' });
      return;
    }
  }

  setStatus({ type: 'loading', title: 'Envoi en cours', message: 'Envoi au VPS…', size: 'inline' });
  try {
    var submittedCount = 0;
    var failedCount = 0;
    for (var j = 0; j < payloads.length; j++) {
      try {
        var cardsApi = window.SchoolSafeCardsNativeAPI;
        if (!cardsApi) throw new Error('API cartes non disponible');
        var res = await cardsApi.submitPrintRequest(payloads[j]);
        if (res && res.data && res.data.status === 'submitted') submittedCount++;
        else failedCount++;
      } catch (e) {
        failedCount++;
        console.error('[cards] Erreur envoi ' + payloads[j].student_id + ': ' + e.message);
      }
      setStatus({ type: 'loading', title: 'Envoi en cours', message: 'Envoi ' + (j + 1) + '/' + payloads.length + '…', size: 'inline' });
    }
    setStatus({ type: 'success', title: 'Envoi terminé', message: 'Envoi terminé : ' + submittedCount + ' soumis, ' + failedCount + ' échec.', size: 'inline' });
    await loadStudents(state.selectedClass.id);
  } catch (e) {
    setStatus({ type: 'error', title: 'Erreur d\'envoi', message: 'Erreur envoi : ' + e.message, size: 'inline' });
  }
}

const CARDS_MODULE_IDS = ["pedagogyModule", "palmaresModule", "financeModule", "accountingModule", "hrModule", "inventoryModule", "communicationModule", "documentCenterModule", "administrationModule", "securityModule", "pilotageModule", "feeControlModule", "accessConsole", "schoolModule", "teacherPedagogyPortal"];

function sessionContext() {
  try {
    const raw = sessionStorage.getItem('schoolsafe-v2-session');
    const session = raw ? JSON.parse(raw) : null;
    const profileId = session && (session.profileId || (session.profile && session.profile.id)) || '';
    return {
      schoolId: session && session.schoolId ? String(session.schoolId) : '',
      profileId: profileId ? String(profileId) : ''
    };
  } catch (e) {
    return { schoolId: '', profileId: '' };
  }
}

function sameContext(snapshot) {
  const current = sessionContext();
  return current.schoolId === snapshot.schoolId && current.profileId === snapshot.profileId;
}

function isStudentUuid(value) {
  return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || ''));
}

function refuseSelection(title, message) {
  state.selectedStudentIds.clear();
  state.selectedClass = null;
  const select = $('cardsClassSelect');
  if (select) select.value = '';
  renderStudentList();
  setStatus({ type: 'error', title: title, message: message, size: 'inline' });
}

function showCardsStudio() {
  initCardsModule();
  const studio = $('cardsStudio');
  if (!studio) return false;
  CARDS_MODULE_IDS.forEach((id) => {
    const element = document.getElementById(id);
    if (element) element.hidden = true;
  });
  studio.hidden = false;
  const grid = document.querySelector('.workspace-grid');
  const protectedEl = document.getElementById('cardsProtected');
  if (grid) grid.style.display = 'none';
  if (protectedEl) protectedEl.style.display = 'none';
  return true;
}

async function applyPreselect(request, recordPromise, generation, snapshot) {
  if (!snapshot.schoolId || !isStudentUuid(request.studentId)) {
    refuseSelection('Session requise', 'Aucun élève réel n’est sélectionné.');
    return;
  }
  try {
    const recordRes = await recordPromise;
    if (generation !== state.openGeneration || !sameContext(snapshot)) return;
    const record = (recordRes && recordRes.data) || {};
    if (!record.id || record.id !== request.studentId) {
      refuseSelection('Élève introuvable', 'Aucun autre élève n’est sélectionné.');
      return;
    }
    if (record.school_id && record.school_id !== snapshot.schoolId) {
      refuseSelection('Élève hors école', "Cet élève n'appartient pas à l'école active.");
      return;
    }
    const classId = record.schooling && record.schooling.class_id;
    if (!classId || (request.classId && request.classId !== classId)) {
      refuseSelection('Classe refusée', request.classId && request.classId !== classId ? 'La classe indiquée ne correspond pas à l’élève.' : 'Classe introuvable.');
      return;
    }
    if (!state.classes.some((item) => item.id === classId)) {
      refuseSelection('Classe non autorisée', 'Cette classe n’est pas disponible pour cette session.');
      return;
    }
    const epoch = state.classEpoch;
    state.selectedClass = state.classes.find((item) => item.id === classId) || null;
    const select = $('cardsClassSelect');
    if (select) select.value = classId;
    await loadStudents(classId);
    if (generation !== state.openGeneration || !sameContext(snapshot) || state.classEpoch !== epoch) return;
    if (!state.selectedClass || state.selectedClass.id !== classId) return;
    const student = state.students.find((item) => item.id === request.studentId);
    if (!student) {
      state.selectedStudentIds.clear();
      renderStudentList();
      setStatus({ type: 'error', title: 'Élève introuvable', message: 'Cet élève n’est pas dans la classe autorisée. Aucun autre élève n’est sélectionné.', size: 'inline' });
      return;
    }
    state.selectedStudentIds.clear();
    state.selectedStudentIds.add(student.id);
    renderStudentList();
    await renderPreview();
  } catch (error) {
    if (generation !== state.openGeneration || !sameContext(snapshot)) return;
    refuseSelection('Ouverture refusée', error && error.message ? error.message : 'Lecture refusée.');
  }
}

async function openCardsStudio(options) {
  const request = options && typeof options === 'object' && options.studentId ? options : null;
  const generation = ++state.openGeneration;
  const snapshot = sessionContext();
  if (!showCardsStudio()) return;
  const recordPromise = request ? nativeApi('/students/' + encodeURIComponent(request.studentId) + '/record') : null;
  await Promise.all([loadSchoolSettings(), loadCardMotto(), loadAssignments(), loadClasses()]);
  if (generation !== state.openGeneration || !sameContext(snapshot)) return;
  if (!request) return;
  await applyPreselect(request, recordPromise, generation, snapshot);
}

function closeCardsStudio() {
  const studio = $('cardsStudio');
  if (studio) studio.hidden = true;
  const grid = document.querySelector('.workspace-grid');
  const protectedEl = document.getElementById('cardsProtected');
  if (grid) grid.style.display = '';
  if (protectedEl) protectedEl.style.display = '';
  if (window.SchoolSafeAppContext && typeof window.SchoolSafeAppContext.restoreAfterOverlay === "function") {
    window.SchoolSafeAppContext.restoreAfterOverlay();
  }
}

export function initCardsModule(options) {
  if (options?.apiBase) state.apiBase = options.apiBase;
  if (window.schoolSafeBackendConfig) {
    state.apiBase = window.schoolSafeBackendConfig.api_base || state.apiBase;
  }

  const studio = $('cardsStudio');
  const closeBtn = $('closeCardsStudio');
  const classSelect = $('cardsClassSelect');
  const renderBtn = $('cardsRenderBtn');
  const requestBtn = $('cardsRequestPrintBtn');
  const buildBatchBtn = $('cardsBuildBatchBtn');
  const selectAll = $('cardsSelectAll');

  if (!studio || !closeBtn || !classSelect || !renderBtn || !requestBtn || !selectAll) {
    console.warn('[cards-module] Éléments du studio non disponibles — init différée.');
    if (!window.__cardsInitObserver) {
      window.__cardsInitObserver = new MutationObserver(() => {
        if (document.getElementById('cardsStudio') && document.getElementById('cardsRequestPrintBtn')) {
          window.__cardsInitObserver.disconnect();
          window.__cardsInitObserver = null;
          initCardsModule(options);
        }
      });
      window.__cardsInitObserver.observe(document.body, { childList: true, subtree: true });
    }
    return;
  }
  if (studio._cardsBound) return;

  closeBtn.addEventListener('click', () => {
    closeCardsStudio();
  });

  classSelect.addEventListener('change', async (e) => {
    state.classEpoch += 1;
    state.productionGeneration += 1;
    state.productionReport = null;
    const classId = e.target.value;
    state.selectedClass = state.classes.find(c => c.id === classId) || null;
    state.selectedStudentIds.clear();
    renderBtn.disabled = true;
    requestBtn.disabled = true;
    $('cardsPreview').innerHTML = window.ssState({ type: 'empty', title: 'Aucun aperçu', message: 'Sélectionnez un ou plusieurs élèves.', size: 'compact' });
    if (state.selectedClass) {
      await loadStudents(classId);
    } else {
      $('cardsStudentList').innerHTML = window.ssState({ type: 'empty', title: 'Aucune classe', message: 'Sélectionnez une classe.', size: 'compact' });
      selectAll.checked = false;
    }
  });

  selectAll.addEventListener('change', () => {
    const completeStudents = state.students.filter(s => isCardInfoComplete(s));
    if (selectAll.checked) {
      completeStudents.forEach(s => state.selectedStudentIds.add(s.id));
    } else {
      state.selectedStudentIds.clear();
    }
    renderStudentList();
  });

  renderBtn.addEventListener('click', renderPreview);
  requestBtn.addEventListener('click', requestPrintBatch);
  if (buildBatchBtn) buildBatchBtn.addEventListener('click', async () => {
    const cardsApi = window.SchoolSafeCardsNativeAPI;
    if (!cardsApi) { setStatus({ type: 'error', title: 'Erreur', message: 'API cartes non disponible.', size: 'inline' }); return; }
    setStatus({ type: 'loading', title: 'Lot en préparation', message: 'Regroupement des cartes en ZIP…', size: 'inline' });
    try {
      const res = await cardsApi.buildBatch({ status: 'submitted' });
      const d = res && res.data;
      if (!d) throw new Error('Réponse serveur invalide');
      setStatus({
        type: 'success',
        title: 'Lot ZIP prêt',
        message: `Lot ${d.batch_id} v${d.version} — ${d.card_count} carte(s), ZIP SHA-256 ${String(d.zip_sha256 || '').slice(0, 12)}…, valable pour Control.`,
        size: 'inline'
      });
    } catch (e) {
      setStatus({ type: 'error', title: 'Erreur de lot', message: 'Erreur préparation lot : ' + e.message, size: 'inline' });
    }
  });
  // ————— Lot 2 : cycle de vie perte/vol —————
  function selectedSingleStudent() {
    const ids = Array.from(state.selectedStudentIds);
    if (ids.length !== 1) return null;
    return state.students.find(s => s.id === ids[0]) || null;
  }

  function requireCardsApi() {
    const cardsApi = window.SchoolSafeCardsNativeAPI;
    if (!cardsApi) setStatus({ type: 'error', title: 'Erreur', message: 'API cartes non disponible.', size: 'inline' });
    return cardsApi || null;
  }

  function cardActionAllowed() {
    var user = window.SchoolSafeAppContext && window.SchoolSafeAppContext.getCurrentUser ? window.SchoolSafeAppContext.getCurrentUser() : {};
    var access = window.SchoolSafeAccess;
    var codes = ["security.card.create", "cards.print.manage", "cards.request.print"];
    if (!access || typeof access.explicitDeny !== "function") return true;
    if (codes.every(function (code) { return access.explicitDeny(user, code); })) return false;
    var live = false;
    try { live = !!window.sessionStorage.getItem("schoolsafe-v2-session"); } catch (e) {}
    if (live && typeof access.canAccessAny === "function" && !access.canAccessAny(user, codes)) return false;
    return true;
  }

  function escapeCard(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (character) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[character];
    });
  }

  function openCardLifecycleModal(config) {
    if (!cardActionAllowed()) {
      setStatus({ type: "error", title: "Accès refusé", message: "Action explicitement refusée ou hors permission.", size: "inline" });
      return;
    }
    if (!window.ssModal) {
      setStatus({ type: "error", title: "Modale indisponible", message: "La modale SchoolSafe n'est pas chargée.", size: "inline" });
      return;
    }
    var student = config.student;
    var identity = student
      ? escapeCard([student.last_name, student.first_name, student.matricule || student.id].filter(Boolean).join(" · "))
      : "Carte concernée";
    var fields = config.fields.map(function (field) {
      return '<label>' + escapeCard(field.label) + '<input id="' + field.id + '" name="' + field.name + '" type="text" ' + (field.max ? 'maxlength="' + field.max + '"' : "") + "></label>";
    }).join("");
    window.ssModal({
      title: config.title,
      subtitle: identity,
      content: '<form id="cardLifecycleForm" class="wiring-card-form" ' + config.marker + '><p>' + identity + "</p>" + fields + "</form>",
      actions: [
        { label: "Annuler", variant: "secondary" },
        {
          label: "Confirmer",
          variant: "primary",
          closeOnClick: false,
          onClick: function (_event, modal) {
            var values = {};
            var invalid = config.fields.some(function (field) {
              var input = document.getElementById(field.id);
              values[field.name] = input ? input.value.trim() : "";
              return field.min ? values[field.name].length < field.min : !values[field.name];
            });
            if (invalid) {
              modal.setError("Renseignez les champs demandés par l'API.");
              return false;
            }
            modal.setError("");
            modal.setLoading(true);
            setStatus({ type: "loading", title: config.loadingTitle, message: config.loadingMessage, size: "inline" });
            config.submit(values).then(function (result) {
              modal.setLoading(false);
              modal.close();
              setStatus({ type: "success", title: config.successTitle, message: config.successMessage(result), size: "inline" });
            }).catch(function (error) {
              modal.setLoading(false);
              modal.setError(error.message);
              setStatus({ type: "error", title: config.errorTitle, message: error.message, size: "inline" });
            });
            return false;
          }
        }
      ]
    });
  }

  const authorizedSelect = $('cardsAuthorizedSelect');
  if (authorizedSelect) authorizedSelect.addEventListener('change', () => {
    const student = selectedSingleStudent();
    if (!student) return;
    state.authorizedByStudent[student.id] = authorizedSelect.value;
    renderPreview();
  });

  const photoInput = $('cardsStudentPhoto');
  if (photoInput) photoInput.addEventListener('change', () => {
    const student = selectedSingleStudent();
    const file = photoInput.files && photoInput.files[0];
    if (!student || !file) {
      setStatus({ type: 'error', title: 'Photo officielle', message: 'Sélectionnez exactement un élève, puis sa photo.', size: 'inline' });
      return;
    }
    setStatus({ type: 'loading', title: 'Photo officielle', message: 'Enregistrement de la photo de l\'élève…', size: 'inline' });
    uploadOfficialPhoto(student, file).then(function () {
      photoInput.value = '';
      return renderPreview();
    }).catch(function (error) {
      setStatus({ type: 'error', title: 'Photo officielle', message: error.message, size: 'inline' });
    });
  });

  const lossReportBtn = $('cardsLossReportBtn');
  if (lossReportBtn) lossReportBtn.addEventListener('click', () => {
    const cardsApi = requireCardsApi(); if (!cardsApi) return;
    const student = selectedSingleStudent();
    if (!student) { setStatus({ type: 'error', title: 'Sélection requise', message: 'Sélectionnez exactement un élève pour signaler une perte/vol.', size: 'inline' }); return; }
    openCardLifecycleModal({
      modal: "loss",
      marker: 'data-card-modal="loss"',
      title: "Motif perte / vol",
      student: student,
      loadingTitle: "Signalement",
      loadingMessage: "Suspension de la carte…",
      successTitle: "Carte suspendue",
      errorTitle: "Erreur signalement",
      fields: [{ id: "cardLossReason", name: "reason", label: "Motif du signalement", min: 3, max: 1000 }],
      successMessage: function (res) {
        const data = res && res.data;
        return "Carte " + (data && data.card_number) + " passée en statut " + (data && data.status) + ".";
      },
      submit: function (values) { return cardsApi.lossReport({ student_id: student.id, reason: values.reason }); }
    });
  });

  const replaceBtn = $('cardsReplaceBtn');
  if (replaceBtn) replaceBtn.addEventListener('click', () => {
    const cardsApi = requireCardsApi(); if (!cardsApi) return;
    const student = selectedSingleStudent();
    if (!student) { setStatus({ type: 'error', title: 'Sélection requise', message: 'Sélectionnez exactement un élève pour remplacer sa carte.', size: 'inline' }); return; }
    openCardLifecycleModal({
      modal: "replace",
      marker: 'data-card-modal="replace"',
      title: "Remplacement de carte",
      student: student,
      loadingTitle: "Remplacement",
      loadingMessage: "Révocation et émission de la nouvelle carte…",
      successTitle: "Carte remplacée",
      errorTitle: "Erreur remplacement",
      fields: [
        { id: "cardReplaceReason", name: "reason", label: "Motif du remplacement", min: 3, max: 1000 },
        { id: "cardReplaceId", name: "old_card_id", label: "Identifiant de la carte à remplacer", min: 1 }
      ],
      successMessage: function (res) {
        const data = res && res.data;
        return "Nouvelle carte " + (data && data.card_number) + " active ; ancienne révoquée.";
      },
      submit: function (values) { return cardsApi.replaceCard({ student_id: student.id, old_card_id: values.old_card_id, reason: values.reason }); }
    });
  });

  const reprintBtn = $('cardsReprintBtn');
  if (reprintBtn) reprintBtn.addEventListener('click', () => {
    const cardsApi = requireCardsApi(); if (!cardsApi) return;
    const student = selectedSingleStudent();
    openCardLifecycleModal({
      modal: "reprint",
      marker: 'data-card-modal="reprint"',
      title: "Réimpression contrôlée",
      student: student,
      loadingTitle: "Réimpression",
      loadingMessage: "Autorisation de réimpression…",
      successTitle: "Réimpression autorisée",
      errorTitle: "Erreur réimpression",
      fields: [
        { id: "cardReprintId", name: "card_id", label: "Identifiant de la carte", min: 1 },
        { id: "cardReprintReason", name: "reason", label: "Motif de la réimpression contrôlée", min: 3, max: 1000 }
      ],
      successMessage: function (res) {
        const data = res && res.data;
        return "Carte " + (data && data.card_number) + " — même credential, réimpression tracée.";
      },
      submit: function (values) { return cardsApi.reprintAuthorize({ card_id: values.card_id, reason: values.reason }); }
    });
  });

  const distributeBtn = $('cardsDistributeBtn');
  if (distributeBtn) distributeBtn.addEventListener('click', () => {
    const cardsApi = requireCardsApi(); if (!cardsApi) return;
    const student = selectedSingleStudent();
    openCardLifecycleModal({
      modal: "distribute",
      marker: 'data-card-modal="distribute"',
      title: "Remise physique",
      student: student,
      loadingTitle: "Distribution",
      loadingMessage: "Enregistrement de la remise…",
      successTitle: "Distribution confirmée",
      errorTitle: "Erreur distribution",
      fields: [{ id: "cardDistributeId", name: "card_id", label: "Identifiant de la carte remise", min: 1 }],
      successMessage: function () { return "Remise de la carte à l'élève enregistrée."; },
      submit: function (values) { return cardsApi.markDistributed({ card_id: values.card_id }); }
    });
  });

  studio._cardsBound = true;
  wireProductionControls();
}

function isOfficialCardQr(value) {
  return /^schoolsafe:\/\/card\/[^/]+\/[^/]+$/.test(value || '');
}

function isRejectedStudentQr(value) {
  return /^schoolsafe:\/\/student\//.test(value || '');
}

function studentLabel(student) {
  return [student && student.last_name, student && student.first_name].filter(Boolean).join(' ') || (student && student.id) || '';
}

function canRequestPrint() {
  const access = window.SchoolSafeAccess;
  const user = window.SchoolSafeAppContext && typeof window.SchoolSafeAppContext.getCurrentUser === 'function'
    ? window.SchoolSafeAppContext.getCurrentUser()
    : null;
  if (!access || !user || typeof access.canAccess !== 'function') return true;
  if (typeof access.explicitDeny === 'function' && access.explicitDeny(user, 'cards.request.print')) return false;
  return access.canAccess(user, 'cards.request.print') === true;
}

function officialGaps(view) {
  const student = (view && view.student) || {};
  const school = (view && view.school) || {};
  const classData = (view && view.classData) || {};
  const gaps = [];
  if (!student.last_name) gaps.push('Nom (élève)');
  if (!student.first_name) gaps.push('Prénom (élève)');
  if (!student.mat) gaps.push('Matricule (élève)');
  if (!student.photo) gaps.push('Photo (élève)');
  if (!classData.name) gaps.push('Classe (classe)');
  if (!view || !view.year) gaps.push('Année scolaire (classe)');
  if (!view || !view.teacher || !view.teacher.name) gaps.push('Professeur titulaire (classe)');
  if (!school.name) gaps.push('Nom de l\'école (école)');
  if (!school.street) gaps.push('Adresse (école)');
  if (!school.phone) gaps.push('Téléphone de l\'école (école)');
  if (!school.logo) gaps.push('Logo (école)');
  if (!school.email) gaps.push('E-mail (école)');
  if (!school.website) gaps.push('Site Internet (école)');
  if (!student.parent_name) gaps.push('Parent principal (famille)');
  if (!student.parent_phone) gaps.push('Téléphone du parent (famille)');
  if (isRejectedStudentQr(student.qr) || !isOfficialCardQr(student.qr)) gaps.push('QR officiel (élève)');
  return gaps;
}

function paintProduction(report) {
  const box = $('cardsPrecontrol');
  const lines = [
    'Élèves : ' + report.total,
    report.admissible == null ? '' : 'Dossiers admissibles : ' + report.admissible,
    report.active == null ? '' : 'Cartes déjà actives : ' + report.active,
    report.firstIssue == null ? '' : 'Premières cartes à émettre : ' + report.firstIssue,
    report.incomplete == null ? '' : 'Dossiers incomplets : ' + report.incomplete,
    report.printable == null ? '' : 'Cartes à produire : ' + report.printable,
    report.foreign ? 'Hors école : ' + report.foreign : '',
    report.errors && report.errors.length ? 'Refus : ' + report.errors.join(' ; ') : '',
    report.reason || ''
  ].filter(Boolean);
  if (box) box.textContent = lines.join('\n');
  const launch = $('cardsLaunchBtn');
  const confirm = $('cardsProductionConfirm');
  if (launch) launch.disabled = !(confirm && confirm.checked) || report.blocked === true;
  setStatus({
    type: report.blocked ? 'warning' : 'success',
    title: report.blocked ? 'Production bloquée' : 'Précontrôle',
    message: lines.join(' '),
    size: 'inline'
  });
}

function storeProduction(report) {
  state.productionReport = report;
  state.productionMode = report.mode;
  paintProduction(report);
  return report;
}

function emptyCounts() {
  return { total: 0, admissible: 0, active: 0, firstIssue: 0, incomplete: 0, printable: 0, foreign: 0, errors: [], rows: [] };
}

function summarizeProduction(mode, rows, snapshot) {
  const report = Object.assign({ mode: mode, blocked: false, reason: '', snapshot: snapshot }, emptyCounts());
  report.rows = rows;
  report.total = rows.length;
  rows.forEach(function (row) {
    if (row.kind === 'active') {
      report.admissible += 1;
      report.active += 1;
      report.printable += 1;
    } else if (row.kind === 'first') {
      report.admissible += 1;
      report.firstIssue += 1;
      report.printable += 1;
    } else if (row.kind === 'incomplete') {
      report.incomplete += 1;
      report.errors.push(row.name + ' : ' + (row.gaps || []).join(', '));
    } else if (row.kind === 'foreign') {
      report.foreign += 1;
      report.errors.push(row.name + ' : autre école');
    } else if (row.kind === 'unofficial') {
      report.errors.push(row.name + ' : QR non officiel');
    } else if (row.kind === 'ineligible') {
      report.errors.push(row.name + ' : non admissible');
    } else {
      report.errors.push(row.name + ' : ' + (row.error || 'refus'));
    }
  });
  return report;
}

function productionStale(generation, snapshot) {
  return generation !== state.productionGeneration || !sameContext(snapshot);
}

async function classifyStudent(student, snapshot, generation) {
  const name = studentLabel(student);
  if (productionStale(generation, snapshot)) return { stale: true };
  if (student.school_id && student.school_id !== snapshot.schoolId) return { id: student.id, name: name, kind: 'foreign' };
  if (student.lifecycle_status && student.lifecycle_status !== 'active') return { id: student.id, name: name, kind: 'ineligible' };
  if (student.class_id && state.selectedClass && student.class_id !== state.selectedClass.id) {
    return { id: student.id, name: name, kind: 'error', error: 'Hors classe' };
  }
  try {
    const credentialRes = await nativeApi('/cards/students/' + encodeURIComponent(student.id) + '/credential');
    if (productionStale(generation, snapshot)) return { stale: true };
    const credential = (credentialRes && credentialRes.data) || {};
    if (credential.card_number && (isRejectedStudentQr(credential.qr_payload) || !isOfficialCardQr(credential.qr_payload))) {
      return { id: student.id, name: name, kind: 'unofficial', gaps: ['QR officiel'] };
    }
    const view = await cardViewFor(student);
    if (productionStale(generation, snapshot)) return { stale: true };
    const gaps = officialGaps(view);
    const identityGaps = gaps.filter(function (gap) { return String(gap).indexOf('QR officiel') !== 0; });
    if (identityGaps.length) return { id: student.id, name: name, kind: 'incomplete', gaps: gaps };
    if (isOfficialCardQr(view.student.qr)) return { id: student.id, name: name, kind: 'active', gaps: [] };
    return { id: student.id, name: name, kind: 'first', gaps: [] };
  } catch (error) {
    if (productionStale(generation, snapshot)) return { stale: true };
    return { id: student.id, name: name, kind: 'error', error: error && error.message ? error.message : 'Lecture refusée' };
  }
}

async function precontrolStudent(generation, snapshot) {
  const ids = Array.from(state.selectedStudentIds);
  if (ids.length !== 1) {
    return storeProduction(Object.assign({ mode: 'student', blocked: false, reason: 'Sélectionnez un seul élève', snapshot: snapshot }, emptyCounts(), { errors: ['Sélection requise'] }));
  }
  const student = state.students.find(function (item) { return item.id === ids[0]; });
  if (!student) {
    return storeProduction(Object.assign({ mode: 'student', blocked: false, reason: 'Élève introuvable', snapshot: snapshot }, emptyCounts(), { errors: ['Élève introuvable'] }));
  }
  const row = await classifyStudent(student, snapshot, generation);
  if (!row || row.stale) return { stale: true };
  return storeProduction(summarizeProduction('student', [row], snapshot));
}

async function precontrolClass(generation, snapshot) {
  if (!state.selectedClass) {
    return storeProduction(Object.assign({ mode: 'class', blocked: false, reason: 'Classe requise', snapshot: snapshot }, emptyCounts(), { errors: ['Classe requise'] }));
  }
  const loaded = await loadStudents(state.selectedClass.id);
  if (loaded === false || productionStale(generation, snapshot)) return { stale: true };
  const rows = [];
  for (let i = 0; i < state.students.length; i++) {
    if (productionStale(generation, snapshot)) return { stale: true };
    const row = await classifyStudent(state.students[i], snapshot, generation);
    if (!row || row.stale) return { stale: true };
    rows.push(row);
    setStatus({ type: 'loading', title: 'Précontrôle', message: 'Précontrôle ' + (i + 1) + '/' + state.students.length + '.', size: 'inline' });
  }
  return storeProduction(summarizeProduction('class', rows, snapshot));
}

async function precontrolSchool(generation, snapshot) {
  await loadClasses();
  if (productionStale(generation, snapshot)) return { stale: true };
  const classCounts = [];
  const refusals = [];
  let total = 0;
  let foreign = 0;
  for (let i = 0; i < state.classes.length; i++) {
    const item = state.classes[i];
    if (productionStale(generation, snapshot)) return { stale: true };
    try {
      const rows = await readStudentPages(item.id, function () { return productionStale(generation, snapshot); });
      if (!rows) return { stale: true };
      const local = [];
      rows.forEach(function (row) {
        if (row.school_id && row.school_id !== snapshot.schoolId) foreign += 1;
        else if (row.class_id && row.class_id !== item.id) refusals.push((row.matricule || row.id) + ' : hors classe');
        else local.push(row);
      });
      total += local.length;
      classCounts.push({ id: item.id, name: item.name, total: local.length });
    } catch (error) {
      if (productionStale(generation, snapshot)) return { stale: true };
      const message = error && error.message ? error.message : 'Lecture refusée';
      refusals.push(item.name + ' : ' + message);
      classCounts.push({ id: item.id, name: item.name, total: 0, error: message });
    }
  }
  return storeProduction({
    mode: 'school',
    blocked: true,
    reason: SCHOOL_PRODUCTION_BLOCK,
    total: total,
    classes: state.classes.length,
    classCounts: classCounts,
    foreign: foreign,
    admissible: null,
    active: null,
    firstIssue: null,
    incomplete: null,
    printable: null,
    errors: refusals,
    rows: [],
    snapshot: snapshot
  });
}

async function precontrolProduction(options) {
  const mode = options && options.mode;
  const generation = ++state.productionGeneration;
  const snapshot = sessionContext();
  if (!snapshot.schoolId) {
    return storeProduction(Object.assign({ mode: mode || '', blocked: true, reason: 'Session requise', snapshot: snapshot }, emptyCounts()));
  }
  if (mode === 'school') return precontrolSchool(generation, snapshot);
  if (mode === 'class') return precontrolClass(generation, snapshot);
  if (mode === 'student') return precontrolStudent(generation, snapshot);
  return storeProduction(Object.assign({ mode: mode || '', blocked: true, reason: 'Mode inconnu', snapshot: snapshot }, emptyCounts()));
}

async function ensureCredential(student, kind, snapshot, generation) {
  const current = await nativeApi('/cards/students/' + encodeURIComponent(student.id) + '/credential');
  if (productionStale(generation, snapshot)) return { stale: true };
  const data = (current && current.data) || {};
  if (data.card_number && (isRejectedStudentQr(data.qr_payload) || !isOfficialCardQr(data.qr_payload))) {
    return { refused: true, error: 'QR non officiel' };
  }
  if (data.card_number && isOfficialCardQr(data.qr_payload)) return { issued: false, credential: data };
  if (kind !== 'first') return { refused: true, error: 'Carte officielle absente' };
  const capsRes = await nativeApi('/scan-control/capabilities');
  if (productionStale(generation, snapshot)) return { stale: true };
  const caps = ((capsRes && capsRes.data) || {}).capabilities || [];
  if (caps.indexOf('CARD_CREATE') < 0) return { refused: true, error: 'Capacité non déléguée' };
  await nativeApi('/scan-control/cards/issue', { method: 'POST', body: { student_id: student.id } });
  if (productionStale(generation, snapshot)) return { stale: true };
  const again = await nativeApi('/cards/students/' + encodeURIComponent(student.id) + '/credential');
  if (productionStale(generation, snapshot)) return { stale: true };
  const next = (again && again.data) || {};
  if (!next.card_number || !isOfficialCardQr(next.qr_payload)) return { refused: true, error: 'Émission non confirmée par le serveur' };
  return { issued: true, credential: next };
}

async function submitOfficialPrint(student) {
  const view = await cardViewFor(student);
  const gaps = officialGaps(view);
  if (gaps.length) return { status: 'incomplete', gaps: gaps };
  const payload = await generateCardPayload(student);
  const serialized = JSON.stringify(payload);
  if (serialized.indexOf('schoolsafe://student/') >= 0) return { status: 'refused', error: 'QR non officiel' };
  const api = window.SchoolSafeCardsNativeAPI;
  if (!api || typeof api.submitPrintRequest !== 'function') return { status: 'failed', error: 'API cartes non disponible' };
  const res = await api.submitPrintRequest(payload);
  const data = (res && res.data) || {};
  const requestId = data.requestId || data.request_id;
  if (data.status !== 'submitted' || !requestId) return { status: 'failed', error: data.error || 'Impression non confirmée', server: data };
  return { status: 'submitted', requestId: requestId, server: data };
}

async function launchProduction(options) {
  const mode = (options && options.mode) || state.productionMode;
  if (mode === 'school') {
    return { blocked: true, refused: true, reason: "Toute l'école : non disponible. Aucune production n'est lancée.", submitted: 0, issued: 0 };
  }
  if (!options || options.confirmed !== true) return { refused: true, reason: 'Confirmation requise' };
  if (state.productionRunning) return { refused: true, reason: 'Production déjà en cours' };
  const report = state.productionReport;
  if (!report || report.mode !== mode) return { refused: true, reason: 'Précontrôle requis' };
  if (!sameContext(report.snapshot)) return { refused: true, reason: 'Session changée' };
  if (mode === 'school' || report.blocked) return { blocked: true, reason: report.reason || SCHOOL_PRODUCTION_BLOCK, submitted: 0, issued: 0 };
  if (!canRequestPrint()) return { refused: true, reason: 'Impression non autorisée', submitted: 0, issued: 0 };
  state.productionRunning = true;
  const generation = state.productionGeneration;
  const snapshot = report.snapshot;
  const results = [];
  const requestIds = [];
  let issued = 0;
  let submitted = 0;
  try {
    for (let i = 0; i < report.rows.length; i++) {
      const row = report.rows[i];
      if (productionStale(generation, snapshot)) {
        results.push({ id: row.id, status: 'stale' });
        break;
      }
      if (state.producedStudentIds.has(row.id)) {
        results.push({ id: row.id, status: 'already' });
        continue;
      }
      if (row.kind !== 'active' && row.kind !== 'first') {
        results.push({ id: row.id, status: row.kind, gaps: row.gaps || [], error: row.error || '' });
        continue;
      }
      const student = state.students.find(function (item) { return item.id === row.id; }) || { id: row.id, first_name: row.name };
      try {
        const ensured = await ensureCredential(student, row.kind, snapshot, generation);
        if (ensured.stale) {
          results.push({ id: row.id, status: 'stale' });
          break;
        }
        if (ensured.refused) {
          results.push({ id: row.id, status: 'refused', error: ensured.error });
          continue;
        }
        if (ensured.issued) issued += 1;
        const printed = await submitOfficialPrint(student);
        if (productionStale(generation, snapshot)) {
          results.push({ id: row.id, status: 'stale' });
          break;
        }
        if (printed.status === 'submitted') {
          state.producedStudentIds.add(student.id);
          submitted += 1;
          requestIds.push(printed.requestId);
          results.push({ id: row.id, status: 'submitted', requestId: printed.requestId });
        } else {
          results.push({ id: row.id, status: printed.status, error: printed.error || '', gaps: printed.gaps || [] });
        }
      } catch (error) {
        if (productionStale(generation, snapshot)) {
          results.push({ id: row.id, status: 'stale' });
          break;
        }
        results.push({ id: row.id, status: 'failed', error: error && error.message ? error.message : 'Échec' });
      }
    }
    let batch = null;
    let batchError = '';
    if (mode === 'class' && requestIds.length && window.SchoolSafeCardsNativeAPI && typeof window.SchoolSafeCardsNativeAPI.buildBatch === 'function') {
      try {
        const res = await window.SchoolSafeCardsNativeAPI.buildBatch({ request_ids: requestIds });
        batch = res && res.data;
      } catch (error) {
        batchError = error && error.message ? error.message : 'Lot non construit';
      }
    }
    const outcome = { mode: mode, blocked: false, submitted: submitted, issued: issued, results: results, batch: batch, batchError: batchError };
    setStatus({
      type: batchError ? 'warning' : 'success',
      title: 'Résultat serveur',
      message: submitted + ' impression(s) confirmée(s), ' + issued + ' première(s) émission(s).' + (batch && batch.batch_id ? ' Lot ' + batch.batch_id + '.' : '') + (batchError ? ' ' + batchError : ''),
      size: 'inline'
    });
    return outcome;
  } finally {
    state.productionRunning = false;
  }
}

function wireProductionControls() {
  const modes = [
    ['cardsModeStudent', 'student'],
    ['cardsModeClass', 'class']
  ];
  modes.forEach(function (entry) {
    const button = $(entry[0]);
    if (!button) return;
    button.addEventListener('click', function () { precontrolProduction({ mode: entry[1] }); });
  });
  const schoolButton = $('cardsModeSchool');
  if (schoolButton) {
    schoolButton.disabled = true;
    schoolButton.textContent = "Toute l'école — Non disponible";
    schoolButton.addEventListener('click', function (event) {
      if (event && event.preventDefault) event.preventDefault();
      setStatus({ type: 'warning', title: 'Non disponible', message: "Toute l'école : à venir. Aucune production n'est lancée.", size: 'inline' });
    });
  }
  const confirm = $('cardsProductionConfirm');
  if (confirm) {
    confirm.addEventListener('change', function () {
      const launch = $('cardsLaunchBtn');
      const report = state.productionReport;
      if (launch) launch.disabled = !confirm.checked || !report || report.blocked === true;
    });
  }
  const launch = $('cardsLaunchBtn');
  if (launch) {
    launch.addEventListener('click', function () {
      if (state.productionMode === 'school') {
        setStatus({ type: 'warning', title: 'Non disponible', message: "Toute l'école : à venir. Aucune production n'est lancée.", size: 'inline' });
        return;
      }
      const box = $('cardsProductionConfirm');
      launchProduction({ mode: state.productionMode, confirmed: !!(box && box.checked) });
    });
  }
}

window.SchoolSafeCards = { init: initCardsModule, open: openCardsStudio, close: closeCardsStudio, precontrol: precontrolProduction, launch: launchProduction };

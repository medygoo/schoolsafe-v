import { renderCardPreview } from './studio-cursor-reference/cards/card-renderer.js';
const form = document.getElementById('nativeLookup');
const status = document.getElementById('cardsStatus');
const preview = document.getElementById('cardsPreview');
const dimensions = document.createElement('style');
dimensions.textContent = '#cardsPreview{display:flex;gap:20px;flex-wrap:wrap}.ss-badge-wrap{width:340px;height:540px;flex:none}.ss-carte-wrap{width:560px;height:353px;flex:none}';
document.head.append(dimensions);
form.addEventListener('submit', async event => {
  event.preventDefault();
  if (!form.reportValidity()) return;
  const button = form.querySelector('button');
  button.disabled = true;
  preview.replaceChildren();
  document.getElementById('cardsStudentList').replaceChildren();
  document.getElementById('cardsClassSelect').replaceChildren();
  status.textContent = 'Chargement Rosario...';
  try {
    const result = await window.SchoolSafeStudioNativeBridge.loadNativeStudioCard(document.getElementById('nativeStudent').value);
    const view = result.view;
    const option = document.createElement('option');
    option.textContent = view.classData?.name || 'Classe non raccordee';
    document.getElementById('cardsClassSelect').append(option);
    const student = document.createElement('p');
    student.textContent = [view.student.first_name, view.student.last_name, view.student.mat].filter(Boolean).join(' - ');
    document.getElementById('cardsStudentList').append(student);
    if (!result.missing.includes('class.mapping') && !result.missing.includes('class.cycle')) {
      renderCardPreview(preview, view.student, view.classData, view.teacher, view.year, view.school, view.school.logo);
    }
    status.textContent = result.missing.length
      ? 'Informations a completer : ' + result.missing.join(', ') + '. Aucune carte emise.'
      : 'Consultation verifiee. Aucune carte emise.';
  } catch {
    status.textContent = 'Consultation refusee ou indisponible. Aucune operation effectuee.';
  } finally { button.disabled = false; }
});

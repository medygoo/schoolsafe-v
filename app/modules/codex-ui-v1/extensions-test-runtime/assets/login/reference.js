import {mountBackpackGreeting} from './backpack-animation.js?v=4';
const host=document.getElementById('ss-login-reference');
const form=document.getElementById('loginform');
if(host&&form){
 const themes=['white','blue','gray','mint','rose','yellow','night'];
 const themeButton=host.querySelector('.ss-login-theme');
 const themeNames={white:'Blanc',blue:'Bleu clair',gray:'Gris',mint:'Menthe',rose:'Rose',yellow:'Jaune',night:'Bleu nuit'};
 function applyTheme(value){
  const theme=themes.includes(value)?value:'night';
  host.dataset.theme=theme;
  document.documentElement.dataset.ssBackground=theme;
  document.documentElement.style.setProperty('--ss-page-background',{white:'#fff',blue:'#dcecff',gray:'#eef0f3',mint:'#e5f5ed',rose:'#fbe9ef',yellow:'#fff5d9',night:'#041b62'}[theme]);
  const next=themes[(themes.indexOf(theme)+1)%themes.length];
  themeButton.title='Changer le fond : '+themeNames[next];
  themeButton.setAttribute('aria-label',themeButton.title);
 }
 try{applyTheme(localStorage.getItem('schoolsafe-background'));}catch{applyTheme('night');}
 themeButton.addEventListener('click',()=>{
  const next=themes[(themes.indexOf(host.dataset.theme)+1)%themes.length];
  applyTheme(next);try{localStorage.setItem('schoolsafe-background',next);}catch{}
 });
 window.addEventListener('storage',event=>{if(event.key==='schoolsafe-background')applyTheme(event.newValue);});
 const slot=document.getElementById('ss-login-form-slot');
 const languages=form.querySelector('a[href*="locale="]')?.closest('tr');
 if(languages){languages.classList.add('ss-login-languages');document.getElementById('ss-login-legal-slot').append(languages);}
 slot.append(form);
 const username=form.querySelector('#USERNAME'),password=form.querySelector('#PASSWORD');
 for(const [input,icon] of [[username,'user-round'],[password,'lock-keyhole']]){
  const label=input.closest('label');const labelText=document.createElement('span');labelText.className='ss-login-sr-only';labelText.textContent=input===username?'Nom d’utilisateur':'Mot de passe';
  label.replaceChildren(labelText,input);label.classList.add('ss-login-field');
  const picture=document.createElement('span');picture.className='ss-login-field-icon';picture.innerHTML='<i data-lucide="'+icon+'"></i>';picture.setAttribute('aria-hidden','true');label.prepend(picture);
  input.autocomplete=input===username?'username':'current-password';
  input.setAttribute('aria-label',input===username?'Nom d’utilisateur':'Mot de passe');
 }
 username.placeholder='Téléphone ou email';username.title='Votre identifiant SchoolSafe enregistré (nom, téléphone ou email)';
 password.placeholder='Mot de passe';
 const toggle=document.createElement('button');toggle.type='button';toggle.className='ss-login-eye';toggle.setAttribute('aria-label','Afficher le mot de passe');toggle.setAttribute('aria-pressed','false');toggle.innerHTML='<i data-lucide="eye-off"></i>';
 password.closest('label').append(toggle);toggle.addEventListener('click',()=>{const reveal=password.type==='password';password.type=reveal?'text':'password';toggle.setAttribute('aria-pressed',String(reveal));toggle.setAttribute('aria-label',reveal?'Masquer le mot de passe':'Afficher le mot de passe');});
 const reset=form.querySelector('a[href="PasswordReset.php"]');if(reset)reset.textContent='Mot de passe oublié ?';
 const original=form.querySelector('input[type="submit"]');const submit=document.createElement('button');submit.type='submit';submit.className='ss-login-submit';submit.innerHTML='<i data-lucide="lock-keyhole"></i><strong>Se connecter</strong><span><i data-lucide="arrow-right"></i></span>';original.replaceWith(submit);
 const school=document.createElement('button');school.type='button';school.className='ss-login-school';school.innerHTML='<i data-lucide="school"></i><span>Connexion école</span><i data-lucide="chevron-right"></i>';school.addEventListener('click',()=>{username.focus();});form.append(school);
 const legal=Array.from(document.querySelectorAll('a[href="schoolsafe-licenses.php"]')).find(e=>!host.contains(e));
 if(legal){
  const notice=legal.closest('p');notice.hidden=true;
  const logo=host.querySelector('.ss-login-brand img');const link=document.createElement('a');
  link.href=legal.getAttribute('href');link.title='Licences et mentions légales';link.setAttribute('aria-label','SchoolSafe — Licences et mentions légales');
  logo.replaceWith(link);link.append(logo);
 }
 const about=document.querySelector('.about-schoolsafe');if(about)about.hidden=true;
 // Keep native error messages inside the form without changing their server-side handling.
 document.querySelectorAll('.error,.note,.updated').forEach(e=>{if(!host.contains(e)&&e.closest('.postbox-wrapper'))slot.prepend(e);});
 window.lucide?.createIcons();document.body.classList.add('ss-login-reference-ready');
 username.removeAttribute('autofocus');if(document.activeElement===username)username.blur();window.scrollTo(0,0);
 const stage=document.getElementById('ss-login-jaspe');
 try{const greeting=await mountBackpackGreeting(stage,form);window.addEventListener('pagehide',()=>greeting.destroy(),{once:true});}
 catch{stage.dataset.engine='backpack-static';}
}

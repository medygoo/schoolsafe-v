(() => {
  const options = { background: true, stripe: true, landscape: true };
  const identity = { mode: 'catalogue', primary: '#2357a4', secondary: '#e5bb36', accent: '#23865a' };
  const colorKeys = ['primary','secondary','accent'];
  const textColors = {};
  const textTargets = {country:'.school .rdc',school:'.school .nm',motto:'.school .slg',labels:'.k,.qr-cap,.sg b',values:'.v',heritage:'.ss-landscape strong,.ss-landscape small'};
  try {
    const saved = JSON.parse(localStorage.getItem('schoolsafe-cursor-studio-text-colors') || '{}');
    Object.keys(textTargets).forEach(key => {if (/^#[0-9a-f]{6}$/i.test(saved[key] || '')) textColors[key] = saved[key];});
  } catch (_) {}
  window.ssSetTextColor = (key,value) => {
    if (!(key in textTargets) || !/^#[0-9a-f]{6}$/i.test(value)) return;
    textColors[key] = value;
    try {localStorage.setItem('schoolsafe-cursor-studio-text-colors',JSON.stringify(textColors));} catch (_) {}
    ssRenderPreview();
  };
  window.ssResetTextColors = () => {
    Object.keys(textColors).forEach(key => delete textColors[key]);
    try {localStorage.removeItem('schoolsafe-cursor-studio-text-colors');} catch (_) {}
    render();
  };
  try {
    const saved = JSON.parse(localStorage.getItem('schoolsafe-cursor-studio-colors') || '{}');
    if (['catalogue','school'].includes(saved.mode)) identity.mode = saved.mode;
    colorKeys.forEach(key => {
      if (/^#[0-9a-f]{6}$/i.test(saved[key] || '')) identity[key] = saved[key];
    });
  } catch (_) {}
  function mix(hex, target, amount) {
    const channels = [1,3,5].map(i => Math.round(parseInt(hex.slice(i,i+2),16) * (1-amount) + target * amount));
    return '#' + channels.map(n => n.toString(16).padStart(2,'0')).join('');
  }
  function readable(hex) {
    const rgb = [1,3,5].map(i => {
      const v = parseInt(hex.slice(i,i+2),16) / 255;
      return v <= .04045 ? v / 12.92 : ((v+.055)/1.055) ** 2.4;
    });
    const luminance = rgb[0]*.2126 + rgb[1]*.7152 + rgb[2]*.0722;
    return (luminance+.05)/.05 >= 1.05/(luminance+.05) ? '#000000' : '#ffffff';
  }
  window.ssSetSchoolColor = (key,value) => {
    if (key === 'mode' && ['catalogue','school'].includes(value)) identity.mode = value;
    else if (colorKeys.includes(key) && /^#[0-9a-f]{6}$/i.test(value)) identity[key] = value;
    else return;
    try { localStorage.setItem('schoolsafe-cursor-studio-colors',JSON.stringify(identity)); } catch (_) {}
    ssRenderPreview();
  };
  const familyHeads = {
    A:['#0d2b7a','#1b3fa0'], B:['#075e63','#0d9488'], C:['#312e81','#4338ca'],
    D:['#0a0f2e','#1a2352'], E:['#2563c4','#4f8ff7'], F:['#8a2f0c','#c2410c'],
    G:['#0f3d24','#1b6b3a'], H:['#141238','#312e81'], I:['#8f1049','#db2777'],
    J:['#1f3a2d','#2c5642'], L:['#1a2228','#556777']
  };
  // Preserve the family's tonal structure while substituting the school's colors.
  function schoolTone(reference, base) {
    const lightness = hex => {
      const channels = [1,3,5].map(i => parseInt(hex.slice(i,i+2),16)/255);
      return (Math.max(...channels)+Math.min(...channels))/2;
    };
    const target = lightness(reference), initial = lightness(base);
    if (target === initial) return base;
    return target > initial ? mix(base,255,(target-initial)/(1-initial)) : mix(base,0,(initial-target)/initial);
  }
  function schoolPalette(family, index) {
    const variants = window._ssCARDS.FVARS[family] || window._ssCARDS.FVARS.L;
    const variant = variants[index] || variants[0];
    const heads = familyHeads[family] || [variants[0].dark,variants[0].cc];
    const base = [identity.primary,identity.secondary,identity.accent,identity.primary][index] || identity.primary;
    const safeHead = hex => {
      let result = hex;
      while (readable(result) !== '#ffffff') result = mix(result,0,.1);
      return result;
    };
    return {
      '--ss-navy': safeHead(schoolTone(heads[0],identity.primary)),
      '--ss-navy2': safeHead(schoolTone(heads[1],identity.primary)),
      '--ss-cc': schoolTone(variant.cc,base),
      '--ss-cc-soft': schoolTone(variant.soft,base),
      '--ss-cc-dark': safeHead(schoolTone(variant.dark,base)),
      '--ss-gold': identity.secondary, '--ss-gold2': mix(identity.secondary,255,.3),
      '--ss-school-ink': '#ffffff'
    };
  }
  window.ssLocalDesignBridge = {
    palette: schoolPalette,
    run(model, callback) {
      const previous = {...options}, mode = identity.mode;
      ['background','stripe','landscape'].forEach(key => {
        if (typeof model[key] === 'boolean') options[key] = model[key];
      });
      if (['catalogue','school'].includes(model.palette)) identity.mode = model.palette;
      try { return callback(); }
      finally { Object.assign(options,previous); identity.mode = mode; }
    }
  };
  const header = { countrySize: 9, schoolSize: 16, mottoSize: 10, width: 100, align: 'left' };
  const headerLimits = {countrySize: [6,18], schoolSize: [8,32], mottoSize: [6,18], width: [40,100]};
  window.ssSetLocalSchoolText = (key,value) => {
    if (!['name','motto','address','phone','email','website'].includes(key)) return;
    DB.settings.school[key] = value;
    try {
      localStorage.setItem('schoolsafe-cursor-studio-school',JSON.stringify(Object.fromEntries(['name','motto','address','phone','email','website'].map(key => [key,DB.settings.school[key] || '']))));
    } catch (_) {}
    ssRenderPreview();
  };
  try {
    const stored = JSON.parse(localStorage.getItem('schoolsafe-cursor-studio-header') || '{}');
    for (const [key,[min,max]] of Object.entries(headerLimits)) {
      if (Number.isFinite(stored[key])) header[key] = Math.max(min,Math.min(max,stored[key]));
    }
    if (['left','center','right'].includes(stored.align)) header.align = stored.align;
  } catch (_) {}
  window.ssSetHeader = (key,value) => {
    if (key === 'align' && ['left','center','right'].includes(value)) header.align = value;
    else if (key in headerLimits && value !== '' && Number.isFinite(Number(value))) {
      const [min,max] = headerLimits[key];
      header[key] = Math.max(min,Math.min(max,Number(value)));
    } else return;
    try { localStorage.setItem('schoolsafe-cursor-studio-header',JSON.stringify(header)); } catch (_) {}
    ssRenderPreview();
  };
  const ribbon = { pattern: 'diagonal', placement: 'both', x: 0, y: 20, width: 100, height: 7 };
  const patterns = {
    diagonal: 'repeating-linear-gradient(135deg,#1aa9df 0 8px,#fff 8px 10px,#f5d230 10px 18px,#fff 18px 20px,#de3743 20px 28px,#fff 28px 30px,#269561 30px 38px,#fff 38px 40px)',
    national: 'linear-gradient(90deg,#1aa9df 0 33.33%,#f5d230 33.33% 66.66%,#de3743 66.66%)',
    chevron: 'repeating-conic-gradient(from 45deg,#269561 0 25%,#f5d230 0 50%,#de3743 0 75%,#1aa9df 0 100%) 0 0 / 16px 16px',
    mosaic: 'repeating-linear-gradient(90deg,#1aa9df 0 10px,#fff 10px 12px,#de3743 12px 22px,#fff 22px 24px,#f5d230 24px 34px,#fff 34px 36px,#269561 36px 46px,#fff 46px 48px)'
  };
  try {
    const saved = JSON.parse(localStorage.getItem('schoolsafe-cursor-studio-ribbon') || '{}');
    if (saved.pattern in patterns) ribbon.pattern = saved.pattern;
    if (['header','bottom','both','free'].includes(saved.placement)) ribbon.placement = saved.placement;
    ['x','y','width','height'].forEach(key => {
      if (Number.isFinite(saved[key])) ribbon[key] = Math.max(key === 'width' || key === 'height' ? 1 : 0, Math.min(key === 'height' ? 40 : 100, saved[key]));
    });
  } catch (_) {}
  window.ssSetRibbon = (key, value) => {
    if (key === 'pattern' && value in patterns) ribbon[key] = value;
    else if (key === 'placement' && ['header','bottom','both','free'].includes(value)) ribbon[key] = value;
    else if (['x','y','width','height'].includes(key)) {
      const number = Number(value);
      if (!Number.isFinite(number)) return;
      ribbon[key] = Math.max(key === 'width' || key === 'height' ? 1 : 0, Math.min(key === 'height' ? 40 : 100, number));
    } else return;
    try { localStorage.setItem('schoolsafe-cursor-studio-ribbon',JSON.stringify(ribbon)); } catch (_) {}
    ssRenderPreview();
  };
  // Geographic references describe the subject, not the location of the artwork.
  const contexts = window.SS_HERITAGE_ORIGINS || {};
  const classTexts = new Map();
  try {
    const stored = JSON.parse(localStorage.getItem('schoolsafe-cursor-studio-heritage-texts') || '[]');
    if (Array.isArray(stored)) stored.forEach(entry => {
      if (Array.isArray(entry) && typeof entry[0] === 'string' && entry[1] && typeof entry[1] === 'object') classTexts.set(entry[0], entry[1]);
    });
  } catch (_) {}
  function selectedClass() {
    const student = DB.students.find(s => s.id === window._ssPreviewSid);
    return DB.classes.find(c => c.id === student?.cid);
  }
  window.ssSetHeritageText = (key, value) => {
    const cl = selectedClass();
    if (!cl || key !== 'group') return;
    const pat = ssGetClassPat(cl, ssClassType(cl).type);
    const id = cl.id + ':' + pat.v;
    classTexts.set(id, {...classTexts.get(id), [key]: value.trim()});
    try { localStorage.setItem('schoolsafe-cursor-studio-heritage-texts', JSON.stringify([...classTexts])); } catch (_) {}
    ssRenderPreview();
  };
  function heritageText(cl, pat) {
    const custom = classTexts.get(cl?.id + ':' + pat.v);
    return {...contexts[pat.v], ...(custom?.group !== undefined ? {group:custom.group} : {})};
  }
  window.ssSetDesignOption = (key, value) => {
    if (!(key in options)) return;
    options[key] = value;
    ssRenderPreview();
  };
  const css = `
    .ss-card-studio .art.ss-configurable {letter-spacing:0;overflow:hidden}
    .ss-configurable .face-tag,.ss-configurable .arc,.ss-configurable .deco {display:none!important}
    .ss-configurable .rdc {font-size:9px!important;letter-spacing:0!important;line-height:1.3}
    .ss-configurable .school {flex:1;min-width:0}
    .ss-configurable .school .nm {overflow-wrap:anywhere;line-height:1.15;letter-spacing:0!important}
    .ss-configurable .head-row {gap:10px;align-items:center}
    .ss-configurable .head {position:relative}
    .ss-configurable.ss-stripe .head::after {content:'';position:absolute;bottom:0;left:0;right:0;height:7px;background:repeating-linear-gradient(135deg,#1aa9df 0 8px,#fff 8px 10px,#f5d230 10px 18px,#fff 18px 20px,#de3743 20px 28px,#fff 28px 30px,#269561 30px 38px,#fff 38px 40px)}
    .ss-configurable .rolebar,.ss-configurable .titlebar {padding:0!important;min-height:0!important;height:0!important;border:0!important}
    .ss-configurable.ss-stripe .rolebar,.ss-configurable.ss-stripe .titlebar {height:7px!important;background:repeating-linear-gradient(135deg,#1aa9df 0 8px,#fff 8px 10px,#f5d230 10px 18px,#fff 18px 20px,#de3743 20px 28px,#fff 28px 30px,#269561 30px 38px,#fff 38px 40px)!important}
    .ss-configurable .patveil {opacity:.22!important}
    .ss-configurable .ss-landscape {position:absolute;bottom:0;left:0;right:0;height:102px;display:flex;align-items:center;gap:10px;background-color:#173e30;background-image:linear-gradient(90deg,rgba(0,0,0,.08),rgba(10,35,20,.6)),var(--heritage-image);background-size:cover;background-position:center;color:white;z-index:25;padding:5px 10px 10px 0;box-sizing:border-box}
    .ss-configurable .ss-landscape img {position:relative;width:125px;height:92px;object-fit:contain;object-position:left bottom;flex:none}
    .ss-configurable .ss-landscape>div {position:relative;flex:1;min-width:0;text-shadow:0 1px 3px #000}
    .ss-configurable.ss-stripe .ss-landscape::after {content:'';position:absolute;bottom:0;left:0;right:0;height:6px;background:repeating-linear-gradient(135deg,#1aa9df 0 8px,#fff 8px 10px,#f5d230 10px 18px,#fff 18px 20px,#de3743 20px 28px,#fff 28px 30px,#269561 30px 38px,#fff 38px 40px)}
    .ss-configurable .ss-landscape strong {display:block;font-size:17px;line-height:1.1;overflow-wrap:anywhere}
    .ss-configurable .ss-landscape small {display:block;font-size:9px;line-height:1.35;margin-top:3px;overflow-wrap:anywhere}
    .ss-configurable .ss-landscape .ss-group {font-size:10px;font-weight:700}
    .ss-configurable.ss-landscape-on.badge:not(.verso) .bodyz {padding-bottom:110px!important}
    .ss-configurable.ss-landscape-on.badge:not(.verso) .foot {display:none!important}
    .ss-configurable.ss-landscape-on.carte .ss-landscape {height:67px;padding:3px 12px 8px 0}
    .ss-configurable.ss-landscape-on.carte .ss-landscape img {width:90px;height:56px}
    .ss-configurable.ss-landscape-on.carte .ss-landscape strong {font-size:13px}
    .ss-configurable.ss-landscape-on.carte:not(.verso) .bodyz {padding-bottom:70px!important}
    .ss-configurable.ss-landscape-on.carte:not(.verso) .foot {display:none!important}
  `;
  function decorate(html, pat, cl, front) {
    const template = document.createElement('template');
    template.innerHTML = html;
    const card = template.content.querySelector('.art');
      card.classList.add('ss-configurable');
      card.querySelectorAll('.sec-note').forEach(el => el.remove());
      if (!front) {
        card.querySelectorAll('.stamp').forEach(stamp => {
          const logo = document.createElement('img');
          logo.src = 'schoolsafe-seal-white.png'; logo.alt = 'SchoolSafe'; logo.className = 'ss-schoolsafe-seal';
          stamp.replaceWith(logo);
        });
        const seal = card.querySelector('.ss-schoolsafe-seal');
        if (seal) {
          const brand = document.createElement('div'); brand.className='ss-verso-brand';
          const slogan = document.createElement('div'); slogan.className='ss-verso-slogan';
          slogan.textContent='Un enfant protégé, un parent informé';
          const signatures = card.querySelector('.sign-row');
          if (signatures) {seal.remove();brand.append(seal,slogan);signatures.replaceWith(brand);}
          else {seal.replaceWith(brand);brand.append(seal,slogan);}
        }
        card.querySelectorAll('.sign-row,.foot').forEach(el => el.remove());
        card.querySelectorAll('.rw').forEach(row => {
          if ([...row.querySelectorAll('.k')].some(el => ['N° Direction','Email','Site web',"Adresse de l'école"].includes(el.textContent.trim()))) row.classList.add('ss-school-contact');
        });
      }
    if (contexts[pat.v]?.museumObject) card.querySelectorAll('.patveil').forEach(el => el.remove());
    if (identity.mode === 'school') {
      const palette = schoolPalette(window._ssCardFam || 'L',window._ssCardVar || 0);
      Object.entries(palette).forEach(([key,value]) => card.style.setProperty(key,value));
      card.classList.add('ss-school-palette');
    }
    card.classList.toggle('ss-stripe', options.stripe);
    card.querySelectorAll('.face-tag,.arc,.deco,.pat').forEach(el => el.remove());
    card.querySelectorAll('.rdc').forEach(el => el.textContent = 'RÉPUBLIQUE DÉMOCRATIQUE DU CONGO');
    const school = card.querySelector('.school');
    if (school) {
      const info = window._schoolInfo();
      school.querySelector('.nm').textContent = info.name || "NOM DE L’ÉCOLE";
      school.querySelectorAll('.slg').forEach(el => el.remove());
      if (info.motto || info.slogan) {
        const motto = document.createElement('div');
        motto.className = 'slg';
        motto.textContent = info.motto || info.slogan;
        school.append(motto);
      }
      school.style.setProperty('--school-size',header.schoolSize + 'px');
      school.style.setProperty('--country-size',header.countrySize + 'px');
      school.style.setProperty('--motto-size',header.mottoSize + 'px');
      school.style.textAlign = header.align;
      school.style.flex = '0 1 ' + header.width + '%';
      school.style.maxWidth = header.width + '%';
    }
    card.querySelectorAll('.rolebar,.titlebar').forEach(el => el.replaceChildren());
    const grid = card.querySelector('.info .grid');
    if (grid) {
      const cell = document.createElement('div');
      cell.className = 'cell';
      const key = document.createElement('div');
      key.className = 'k';
      key.textContent = 'Classe';
      const value = document.createElement('div');
      value.className = 'v';
      value.textContent = cl?.name || '';
      cell.append(key, value);
      grid.prepend(cell);
    }
    if (options.stripe && !card.querySelector('.rolebar,.titlebar')) {
      const bar = document.createElement('div');
      bar.className = 'rolebar';
      card.querySelector('.head').after(bar);
    }
    if (options.landscape && front) {
      card.classList.add('ss-landscape-on');
      const band = document.createElement('div');
      band.className = 'ss-landscape';
      const context = heritageText(cl, pat);
      if (context.museumObject) band.classList.add('ss-museum-heritage');
      if (context.landscapeOnly) band.classList.add('ss-site-landscape');
        band.style.setProperty('--heritage-image', `url('patrimoine/${context.background || context.image || pat.v + '.png'}')`);
      const image = document.createElement('img');
      image.src = 'patrimoine/' + (context.image || pat.v + '.png');
      image.alt = pat.n;
      const text = document.createElement('div');
      const name = document.createElement('strong');
      name.textContent = pat.n.toUpperCase();
      const caption = document.createElement('small');
      caption.textContent = context.location || 'Provenance à renseigner';
      const group = document.createElement('small');
      group.className = 'ss-group';
      group.textContent = [cl?.name, context.group || cl?.card_group_name || pat.n].filter(Boolean).join(' · ');
      const signature = document.createElement('small');
      signature.textContent = context.credit || 'SchoolSafe';
      text.append(name, caption, group, signature);
      if (!context.landscapeOnly) band.append(image);
      band.append(text);
      card.append(band);
    }
    const style = document.createElement('style');
    style.textContent = css + `
      .ss-configurable.ss-stripe .head::after,.ss-configurable.ss-stripe .ss-landscape::after {display:none!important}
      .ss-configurable.ss-stripe .rolebar,.ss-configurable.ss-stripe .titlebar {height:0!important;background:none!important}
      .ss-configurable .ss-ribbon {position:absolute;z-index:30;pointer-events:none;box-sizing:border-box}
      .ss-configurable .school .rdc {font-size:var(--country-size)!important;white-space:normal;overflow-wrap:anywhere}
      .ss-configurable .school .nm {font-size:var(--school-size)!important;white-space:normal;line-height:1.15}
      .ss-configurable .school .slg {font-size:var(--motto-size)!important;white-space:normal;overflow-wrap:anywhere;line-height:1.2;letter-spacing:0!important;margin-top:4px}
      .ss-configurable .head-row,.ss-configurable.carte .head {display:flex;align-items:center;gap:10px}
      .ss-configurable.badge .head {height:100px;min-height:100px;max-height:100px;flex:none;padding:12px 16px!important;box-sizing:border-box}
      .ss-configurable.badge .head-row {height:100%;min-height:0}
      .ss-configurable.carte .head {height:80px;min-height:80px;max-height:80px;flex:none;padding:10px 16px!important;box-sizing:border-box}
      .ss-configurable .head .flag,.ss-configurable .head .logo-slot {flex-shrink:0}
      .ss-configurable .ss-site-landscape {padding-left:14px;background-position:center 55%}
      .ss-configurable .ss-site-landscape>div {max-width:70%;margin-left:auto}
      .ss-configurable .ss-museum-heritage {background-color:#26312d;background-image:linear-gradient(90deg,rgba(15,24,20,.12),rgba(15,24,20,.88)),var(--heritage-image);background-position:center 40%;color:#fff;padding-left:8px;overflow:hidden}
      .ss-configurable .ss-museum-heritage>div {text-shadow:0 1px 3px #101b16;padding:0 2px}
      .ss-configurable .ss-museum-heritage img {width:90px;object-position:center;mask-image:linear-gradient(90deg,#000 70%,transparent);-webkit-mask-image:linear-gradient(90deg,#000 70%,transparent)}
      .ss-configurable .ss-museum-heritage strong {font-size:13px}
      .ss-configurable .ss-museum-heritage small {font-size:8px}
      .ss-configurable .ss-schoolsafe-seal {width:64px;height:64px;object-fit:contain;flex:none;display:block;border-radius:4px}
      .ss-configurable.verso .ss-school-contact {flex-wrap:wrap;gap:4px 7px}
      .ss-configurable.verso .ss-school-contact .grow {min-width:0;flex:1}
      .ss-configurable.verso .ss-school-contact .v {white-space:normal;overflow-wrap:anywhere;line-height:1.2}
      .ss-configurable.verso .ss-school-contact>div:last-child:not(.grow):not(.ic) {flex-basis:100%;padding-left:28px}
      .ss-configurable.verso .rows {gap:4px}
      .ss-configurable.verso .sign-row {margin-top:8px}
      .ss-configurable.verso .ss-verso-brand {display:flex;align-items:center;justify-content:center;gap:12px;margin-top:auto;padding:8px 0 10px;flex:none}
      .ss-configurable.verso .ss-verso-brand .ss-schoolsafe-seal {width:76px;height:76px}
      .ss-configurable.verso .ss-verso-slogan {font-size:11px;font-weight:800;line-height:1.35;max-width:150px;color:#263d50;overflow-wrap:anywhere}
      .ss-configurable.carte.verso .ss-verso-brand {flex-direction:column;gap:4px;margin-top:0;padding:4px 0}
      .ss-configurable.carte.verso .ss-verso-brand .ss-schoolsafe-seal {width:64px;height:64px}
      .ss-configurable.carte.verso .ss-verso-slogan {font-size:8px;text-align:center;max-width:120px}
      .ss-configurable.ss-school-palette .head {color:var(--ss-school-ink)!important}
      .ss-configurable.ss-school-palette .head .nm,.ss-configurable.ss-school-palette .head .rdc,.ss-configurable.ss-school-palette .head .slg {color:var(--ss-school-ink)!important}
      `;
      Object.entries(textColors).forEach(([key,value]) => {
        style.textContent += textTargets[key].split(',').map(selector => `.ss-configurable ${selector}{color:${value}!important}`).join('\n');
        card.querySelectorAll(textTargets[key]).forEach(el => el.style.setProperty('color',value,'important'));
      });
    card.prepend(style);
    if (options.stripe) {
      const addRibbon = position => {
        const strip = document.createElement('div');
        strip.className = 'ss-ribbon';
        strip.setAttribute('aria-hidden', 'true');
        strip.style.background = patterns[ribbon.pattern];
        strip.style.height = ribbon.height + 'px';
        strip.style.left = (position === 'free' ? ribbon.x : 0) + '%';
        strip.style.width = (position === 'free' ? Math.min(ribbon.width, 100 - ribbon.x) : 100) + '%';
        if (position === 'bottom') strip.style.bottom = '0';
        else strip.style.top = position === 'free' ? ribbon.y + '%' : '100%';
        if (position === 'header') {
          strip.style.top = 'auto';
          strip.style.bottom = '0';
          card.querySelector('.head').append(strip);
        } else card.append(strip);
      };
      if (ribbon.placement === 'both') ['header','bottom'].forEach(addRibbon);
      else addRibbon(ribbon.placement);
    }
    const studio = document.getElementById('ss-card-studio');
    if (studio) {
      const measure = document.createElement('div');
      measure.style.cssText = 'position:fixed;left:-10000px;top:0;visibility:hidden;pointer-events:none';
      measure.append(card);
      studio.append(measure);
      fitSchoolHeader(card);
      template.content.append(card);
      measure.remove();
    }
    return template.innerHTML;
  }
  function fitSchoolHeader(root) {
    root.querySelectorAll('.school').forEach(school => {
      const name = school.querySelector('.nm');
      const head = school.closest('.head');
      if (!name || !head) return;
      let size = header.schoolSize;
      const available = head.clientHeight - 24;
      while (size > 8 && (school.scrollHeight > available || name.scrollWidth > school.clientWidth)) {
        size -= .5;
        school.style.setProperty('--school-size',size + 'px');
      }
    });
  }
  ['ssBuildBadge', 'ssBuildCarte'].forEach(key => {
    const original = window[key];
    window[key] = function(s, cl, teacher, year, pat) {
      const built = original(s, cl, teacher, year, pat, options.background && !contexts[pat.v]?.museumObject ? 'fond' : 'none');
      return {...built, recto: decorate(built.recto, pat, cl, true), verso: decorate(built.verso, pat, cl, false)};
    };
  });
  const renderCards = R.cards;
  R.cards = function(...args) {
    const template = document.createElement('template');
    template.innerHTML = renderCards.apply(this, args);
    const previous = template.content.querySelector('#ss-pat-style');
    if (previous) {
      const controls = document.createElement('div');
      controls.style.cssText = 'display:flex;gap:12px;flex-wrap:wrap;align-items:center';
      [['background','Fond patrimonial'],['stripe','Barre culturelle'],['landscape','Photo du patrimoine']].forEach(([key, name]) => {
        const label = document.createElement('label');
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = options[key];
        if (options[key]) input.setAttribute('checked', '');
        input.setAttribute('onchange', `ssSetDesignOption('${key}',this.checked)`);
        label.append(input, document.createTextNode(' ' + name));
        controls.append(label);
      });
      previous.replaceWith(controls);
      const fields = document.createElement('div');
      fields.style.cssText = 'display:flex;gap:10px;flex-wrap:wrap;width:100%';
      [['location','Lieu / province'],['group','Nom du groupe']].forEach(([key, title]) => {
        const label = document.createElement('label');
        label.style.cssText = 'flex:1;min-width:160px;font-size:12px';
        label.textContent = title;
        const input = document.createElement('input');
        input.id = 'ss-heritage-' + key;
        input.className = 'form-input';
        if (key === 'location') input.setAttribute('readonly','');
        input.setAttribute('oninput', `ssSetHeritageText('${key}',this.value)`);
        label.append(input);
        fields.append(label);
      });
      controls.after(fields);
      const originDetails = document.createElement('div');
      originDetails.id = 'ss-origin-details';
      originDetails.style.cssText = 'width:100%;font-size:12px;line-height:1.5;overflow-wrap:anywhere';
      fields.after(originDetails);
      const ribbonFields = document.createElement('fieldset');
      ribbonFields.style.cssText = 'display:flex;gap:10px;flex-wrap:wrap;align-items:end;width:100%;border:1px solid #d7dce3;padding:10px;margin:6px 0';
      const legend = document.createElement('legend');
      legend.textContent = 'Bandeaux';
      ribbonFields.append(legend);
      function field(title, input) {
        const label = document.createElement('label');
        label.style.cssText = 'font-size:12px;display:flex;flex-direction:column;gap:4px;flex:1;min-width:' + (input.tagName === 'SELECT' ? '180px' : '100px');
        label.append(document.createTextNode(title), input);
        ribbonFields.append(label);
      }
      [['pattern','Motif',[['diagonal','Diagonales'],['national','Bleu · jaune · rouge'],['chevron','Chevrons'],['mosaic','Mosaïque']]],['placement','Emplacement',[['header','Sous l’en-tête'],['bottom','Bas de la carte'],['both','En-tête et bas'],['free','Position libre']]]].forEach(([key,title,values]) => {
        const select = document.createElement('select');
        select.className = 'form-input';
        select.setAttribute('onchange', `ssSetRibbon('${key}',this.value)`);
        values.forEach(([value,name]) => {
          const option = document.createElement('option');
          option.value = value;
          option.textContent = name;
          if (value === ribbon[key]) option.setAttribute('selected','');
          select.append(option);
        });
        field(title,select);
      });
      [['x','X (%)'],['y','Y (%)'],['width','Largeur (%)'],['height','Épaisseur (px)']].forEach(([key,title]) => {
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'form-input';
        input.min = key === 'width' || key === 'height' ? '1' : '0';
        input.max = key === 'height' ? '40' : '100';
        input.value = ribbon[key];
        input.setAttribute('value',ribbon[key]);
        input.setAttribute('oninput',`ssSetRibbon('${key}',this.value)`);
        if (key !== 'height' && ribbon.placement !== 'free') input.setAttribute('disabled','');
        field(title,input);
      });
      fields.after(ribbonFields);
      const headerFields = document.createElement('fieldset');
      headerFields.style.cssText = ribbonFields.style.cssText;
      const headerLegend = document.createElement('legend');
      headerLegend.textContent = 'Nom de l’école et devise';
      headerFields.append(headerLegend);
      [['name','Nom de l’école'],['motto','Devise de l’école']].forEach(([key,title]) => {
        const label = document.createElement('label');
        label.style.cssText = 'font-size:12px;display:flex;flex-direction:column;gap:4px;width:100%';
        label.textContent = title;
        const input = document.createElement('input');
        input.id = 'ss-school-' + key;
        input.className = 'form-input';
        input.setAttribute('value',window._schoolInfo()[key] || '');
        input.setAttribute('oninput',`ssSetLocalSchoolText('${key}',this.value)`);
        label.append(input);
        headerFields.append(label);
      });
      [['countrySize','Taille du pays (px)'],['schoolSize','Taille du nom (px)'],['mottoSize','Taille de la devise (px)'],['width','Largeur du bloc (%)']].forEach(([key,title]) => {
        const label = document.createElement('label');
        label.style.cssText = 'display:flex;flex-direction:column;gap:4px;font-size:12px;flex:1;min-width:150px';
        label.textContent = title;
        const input = document.createElement('input');
        input.type = 'number';
        input.id = 'ss-header-' + key;
        input.className = 'form-input';
        input.min = headerLimits[key][0];
        input.max = headerLimits[key][1];
        input.setAttribute('value',header[key]);
        input.setAttribute('oninput',`ssSetHeader('${key}',this.value)`);
        label.append(input);
        headerFields.append(label);
      });
      const alignLabel = document.createElement('label');
      alignLabel.textContent = 'Alignement du nom et de la devise';
      alignLabel.style.cssText = 'font-size:12px;display:flex;flex-direction:column;gap:4px;width:100%';
      const align = document.createElement('select');
      align.id = 'ss-header-align';
      align.className = 'form-input';
      align.setAttribute('onchange',"ssSetHeader('align',this.value)");
      [['left','Gauche'],['center','Centre'],['right','Droite']].forEach(([value,title]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = title;
        if (value === header.align) option.setAttribute('selected','');
        align.append(option);
      });
      alignLabel.append(align);
      headerFields.append(alignLabel);
      ribbonFields.after(headerFields);
      const paletteFields = document.createElement('fieldset');
      paletteFields.id = 'ss-school-palette-controls';
      paletteFields.style.cssText = ribbonFields.style.cssText;
      const paletteLegend = document.createElement('legend');
      paletteLegend.id = 'ss-school-palette-name';
      paletteLegend.textContent = 'Palette : ' + (window._schoolInfo().name || 'Mon école');
      paletteFields.append(paletteLegend);
      const sourceLabel = document.createElement('label');
      sourceLabel.textContent = 'Palette utilisée ';
      const source = document.createElement('select');
      source.id = 'ss-school-color-mode';
      source.className = 'form-input';
      source.setAttribute('onchange',"ssSetSchoolColor('mode',this.value)");
      [['catalogue','Catalogue'],['school','Couleurs de l’école']].forEach(([value,title]) => {
        const item = document.createElement('option');
        item.value = value;
        item.textContent = title;
        if (identity.mode === value) item.setAttribute('selected','');
        source.append(item);
      });
      sourceLabel.append(source);
      paletteFields.append(sourceLabel);
      [['primary','Principale'],['secondary','Secondaire'],['accent','Accent complémentaire']].forEach(([key,title]) => {
        const label = document.createElement('label');
        label.style.cssText = 'display:flex;align-items:center;gap:8px;font-size:12px';
        label.textContent = title;
        const input = document.createElement('input');
        input.type = 'color';
        input.id = 'ss-school-color-' + key;
        input.setAttribute('value',identity[key]);
        input.setAttribute('oninput',`ssSetSchoolColor('${key}',this.value)`);
        input.style.cssText = 'width:44px;height:32px;padding:2px;cursor:pointer';
        label.append(input);
        paletteFields.append(label);
      });
      headerFields.before(paletteFields);
      const textFields = document.createElement('fieldset');
      textFields.style.cssText = ribbonFields.style.cssText;
      const textLegend = document.createElement('legend'); textLegend.textContent = 'Couleurs des textes'; textFields.append(textLegend);
      [['country','Pays','#e8c95c'],['school','Nom de l’école','#ffffff'],['motto','Devise','#ffffff'],['labels','Libellés','#556777'],['values','Informations','#17203a'],['heritage','Patrimoine','#ffffff']].forEach(([key,title,base]) => {
        const label = document.createElement('label'); label.textContent = title+' ';
        const input = document.createElement('input'); input.type='color'; input.value=textColors[key]||base;
        input.setAttribute('value',input.value); input.setAttribute('oninput',`ssSetTextColor('${key}',this.value)`);
        label.append(input); textFields.append(label);
      });
      const reset = document.createElement('button'); reset.type='button'; reset.textContent='Couleurs du design'; reset.setAttribute('onclick','ssResetTextColors()'); textFields.append(reset);
      headerFields.after(textFields);
      const contactFields = document.createElement('fieldset'); contactFields.style.cssText=ribbonFields.style.cssText;
      const contactLegend = document.createElement('legend'); contactLegend.textContent='Coordonnées de l’école'; contactFields.append(contactLegend);
      [['address','Adresse'],['phone','Numéro de direction'],['email','Email'],['website','Site de l’école']].forEach(([key,title]) => {
        const label = document.createElement('label'); label.textContent=title+' '; label.style.cssText='flex:1;min-width:160px';
        const input=document.createElement('input'); input.type='text'; input.className='form-input'; input.setAttribute('value',window._schoolInfo()[key] || '');
        input.setAttribute('oninput',`ssSetLocalSchoolText('${key}',this.value)`); label.append(input); contactFields.append(label);
      });
      textFields.after(contactFields);
    }
    return template.innerHTML;
  };
  const preview = window.ssRenderPreview;
  window.ssRenderPreview = function(...args) {
    const result = preview.apply(this, args);
    const studio = document.getElementById('ss-card-studio');
    if (studio) fitSchoolHeader(studio);
    const paletteName = document.getElementById('ss-school-palette-name');
    if (paletteName) paletteName.textContent = 'Palette : ' + (window._schoolInfo().name || 'Mon école');
    document.querySelectorAll('.ss-var-btn').forEach(button => {
      button.style.background = identity.mode === 'school'
        ? schoolPalette(window._ssCardFam || 'L',Number(button.dataset.idx))['--ss-cc']
        : button.dataset.cc;
    });
    document.querySelectorAll('input[oninput*="ssSetRibbon"]').forEach(input => {
      input.disabled = !input.getAttribute('oninput').includes("'height'") && ribbon.placement !== 'free';
    });
    const cl = selectedClass();
    if (cl) {
      const pat = ssGetClassPat(cl, ssClassType(cl).type);
      const context = heritageText(cl, pat);
      const details = document.getElementById('ss-origin-details');
      if (details) {
        details.replaceChildren();
        if (context.note) details.append(document.createTextNode(context.note + ' '));
        if (context.source) {
          const link = document.createElement('a');
          link.href = context.source;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.textContent = 'Source géographique';
          details.append(link);
        }
        if (context.license) {
          const link = document.createElement('a');
          link.href = context.license;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.textContent = ' · Licence de l’image';
          details.append(link);
        }
      }
      ['location', 'group'].forEach(key => {
        const input = document.getElementById('ss-heritage-' + key);
        if (input && document.activeElement !== input) input.value = context[key] || (key === 'group' ? cl.card_group_name || pat.n : '');
      });
    }
    return result;
  };
})();

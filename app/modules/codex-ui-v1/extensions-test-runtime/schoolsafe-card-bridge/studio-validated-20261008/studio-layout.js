(() => {
  'use strict';
  const storage='schoolsafe-cursor-studio-layout-v1';
  const settings={width:{badge:54,carte:85.6},elements:{badge:{},carte:{}}};
  const targets={country:'.school .rdc',school:'.school .nm',motto:'.school .slg',values:'.v',labels:'.k',photo:'.photo',schoolLogo:'.logo-slot',brandLogo:'.ss-schoolsafe-seal',heritage:'.ss-landscape>div'};
  const titles={country:'Pays',school:'Nom de l’école',motto:'Devise',values:'Informations',labels:'Libellés',photo:'Photo',schoolLogo:'Logo de l’école',brandLogo:'Logo SchoolSafe',heritage:'Texte du patrimoine'};
  let editing=false,physicalPreview=false,selected='school',gesture=null;
  try {
    const saved=JSON.parse(localStorage.getItem(storage)||'{}');
    for(const type of ['badge','carte']) {
      if(Number.isFinite(saved.width?.[type]))settings.width[type]=Math.max(40,Math.min(120,saved.width[type]));
      for(const id of Object.keys(targets)) {
        const v=saved.elements?.[type]?.[id];if(!v)continue;
        settings.elements[type][id]={};
        for(const field of ['x','y','size','width'])if(Number.isFinite(v[field]))settings.elements[type][id][field]=Math.max(field==='size'?8:field==='width'?24:-150,Math.min(field==='size'?36:field==='width'?200:150,v[field]));
      }
    }
  }catch(_){}
  const save=()=>{try{localStorage.setItem(storage,JSON.stringify(settings));}catch(_){toast('Disposition non enregistrée','warning');}};
  const typeOf=card=>card.classList.contains('carte')?'carte':'badge';
  function apply(card) {
    const type=typeOf(card),pixelWidth=type==='badge'?340:560,pixelHeight=type==='badge'?540:353;
    card.dataset.physicalWidth=settings.width[type];
    const host=card.closest('.ss-card-studio');
    if(host){host.dataset.printFace='true';host.classList.toggle('ss-preview-physical',physicalPreview);host.style.setProperty('--print-width',settings.width[type]+'mm');host.style.setProperty('--print-height',(settings.width[type]*pixelHeight/pixelWidth)+'mm');host.style.setProperty('--print-scale',settings.width[type]*96/25.4/pixelWidth);}
    for(const [id,selector] of Object.entries(targets))card.querySelectorAll(selector).forEach(el=>{
      el.dataset.layout=id;
      const value=settings.elements[type][id]||{};
      if(value.x!==undefined||value.y!==undefined){el.style.position='relative';el.style.translate=`${value.x||0}px ${value.y||0}px`;}
      if(value.size!==undefined)el.style.setProperty('font-size',value.size+'px','important');
      if(value.width!==undefined){
        if(['photo','schoolLogo','brandLogo'].includes(id)) {
          const ratio=id==='photo'?(type==='badge'?126/150:110/128):1;
          el.style.setProperty('width',value.width+'px','important');el.style.setProperty('height',value.width/ratio+'px','important');
          el.style.flexShrink='0';
          el.querySelectorAll('img').forEach(img=>{img.style.width='100%';img.style.height='100%';img.style.objectFit='contain';});
        }else{el.style.width=value.width+'px';el.style.maxWidth='100%';}
      }
      if(!['photo','schoolLogo','brandLogo'].includes(id)) {el.style.whiteSpace='normal';el.style.overflowWrap='anywhere';}
    });
    card.querySelectorAll('[id^="ss-qr-"]').forEach(el=>el.dataset.layoutLocked='true');
    const style=document.createElement('style');style.textContent=`@media print{.ss-card-studio[data-print-face]{width:var(--print-width)!important;height:var(--print-height)!important;overflow:visible!important}.ss-card-studio[data-print-face]>.art{transform:scale(var(--print-scale));transform-origin:top left;box-shadow:none!important;print-color-adjust:exact;-webkit-print-color-adjust:exact}}`;card.prepend(style);
    style.textContent+='.ss-preview-physical[data-print-face]{width:var(--print-width)!important;height:var(--print-height)!important;overflow:visible!important}.ss-preview-physical[data-print-face]>.art{transform:scale(var(--print-scale));transform-origin:top left}';
  }
  ['ssBuildBadge','ssBuildCarte'].forEach(key=>{
    const original=window[key];window[key]=function(...args){const built=original.apply(this,args);for(const face of ['recto','verso']){const t=document.createElement('template');t.innerHTML=built[face];apply(t.content.querySelector('.art'));built[face]=t.innerHTML;}return built;};
  });
  function currentType(){return typeOf(document.querySelector('#ss-card-studio .art')||document.createElement('div'));}
  function sync() {
    const type=currentType(),v=settings.elements[type][selected]||{},input=document.getElementById('ss-layout-physical');
    if(input)input.value=settings.width[type];
    const dimension=document.getElementById('ss-layout-dimension');
    if(dimension)dimension.textContent=`${settings.width[type].toFixed(1)} × ${(settings.width[type]*(type==='badge'?540/340:353/560)).toFixed(1)} mm`;
    for(const field of ['x','y','size','width']) {
      const el=document.getElementById('ss-layout-'+field);if(!el)continue;
      const node=document.querySelector('#ss-card-studio [data-layout="'+selected+'"]');
      el.value=v[field]??(field==='size'?(node?parseFloat(getComputedStyle(node).fontSize):12):field==='width'?(node?Math.round(parseFloat(getComputedStyle(node).width)):100):0);
      el.disabled=field==='size'&&['photo','schoolLogo','brandLogo'].includes(selected);
    }
    const warnings=[];
    document.querySelectorAll('#ss-card-studio .art').forEach(card=>{
      const box=card.getBoundingClientRect(),width=settings.width[typeOf(card)];
      card.querySelectorAll('.v,.k,.school .nm,.ss-verso-slogan').forEach(el=>{
        if(!el.textContent.trim())return;
        const r=el.getBoundingClientRect();
        if(r.bottom>box.bottom+1||r.right>box.right+1||r.left<box.left-1||el.scrollHeight>el.clientHeight+2||el.scrollWidth>el.clientWidth+2)warnings.push('Contenu hors zone : '+el.textContent.trim().slice(0,32));
        const points=parseFloat(getComputedStyle(el).fontSize)*width/(typeOf(card)==='badge'?340:560)*72/25.4;
        if(points<6)warnings.push('Texte inférieur à 6 pt au format choisi');
      });
      card.querySelectorAll('[data-layout="photo"],[data-layout="schoolLogo"],[data-layout="brandLogo"]').forEach(el=>{const r=el.getBoundingClientRect();if(r.bottom>box.bottom+1||r.right>box.right+1||r.left<box.left-1||r.top<box.top-1)warnings.push('Image hors de la carte');});
      card.classList.toggle('ss-layout-editing',editing);
      card.querySelectorAll('[data-layout]').forEach(el=>{
        el.classList.toggle('ss-layout-selected',editing&&el.dataset.layout===selected);
        if(editing&&el.dataset.layout===selected){
          if(getComputedStyle(el).position==='static')el.style.position='relative';
          for(const mode of ['move','resize']) {const handle=document.createElement('span');handle.className='ss-layout-handle ss-layout-'+mode;handle.dataset.handle=mode;handle.title=mode==='move'?'Déplacer':'Redimensionner';handle.setAttribute('aria-label',handle.title);el.append(handle);}
        }
      });
    });
    const status=document.getElementById('ss-layout-alert');if(status)status.textContent=[...new Set(warnings)].slice(0,6).join(' · ')||'Aucun débordement détecté';
  }
  window.ssLayoutChoose=value=>{selected=value;ssRenderPreview();};
  window.ssLayoutEdit=on=>{editing=on;ssRenderPreview();};
  window.ssLayoutPhysical=on=>{physicalPreview=on;ssRenderPreview();};
  window.ssLayoutSet=(field,value)=>{
    const type=currentType(),n=Number(value);if(!Number.isFinite(n))return;
    if(field==='physical')settings.width[type]=Math.max(40,Math.min(120,n));
    else{settings.elements[type][selected]||={};settings.elements[type][selected][field]=Math.max(field==='size'?8:field==='width'?24:-150,Math.min(field==='size'?36:field==='width'?200:150,n));}
    save();ssRenderPreview();
  };
  window.ssLayoutReset=()=>{delete settings.elements[currentType()][selected];save();ssRenderPreview();};
  window.ssLayoutApply=()=>{
    const type=currentType();settings.elements[type][selected]||={};
    for(const field of ['x','y','size','width']) {
      const input=document.getElementById('ss-layout-'+field),n=Number(input.value);if(input.disabled||!Number.isFinite(n))continue;
      settings.elements[type][selected][field]=Math.max(field==='size'?8:field==='width'?24:-150,Math.min(field==='size'?36:field==='width'?200:150,n));
    }
    const width=Number(document.getElementById('ss-layout-physical').value);if(Number.isFinite(width))settings.width[type]=Math.max(40,Math.min(120,width));
    save();ssRenderPreview();
  };
  const renderPreview=window.ssRenderPreview;
  window.ssRenderPreview=function(...args){const result=renderPreview.apply(this,args);document.querySelectorAll('#ss-card-studio .art').forEach(apply);sync();return result;};
  const renderer=R.cards;
  R.cards=function(...args){
    const html=renderer.apply(this,args);
    if(!html.includes('ss-card-studio'))return html;
    const panel=`<fieldset style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0;padding:12px;border:1px solid #ccd4db"><legend>Disposition et impression</legend><label><input type="checkbox" ${editing?'checked':''} onchange="ssLayoutEdit(this.checked)"> Modifier sur la carte</label><label>Élément <select onchange="ssLayoutChoose(this.value)">${Object.entries(titles).map(([id,title])=>`<option value="${id}" ${id===selected?'selected':''}>${title}</option>`).join('')}</select></label><label>Largeur imprimée (mm) <input id="ss-layout-physical" type="number" min="40" max="120" step="0.1" onchange="ssLayoutSet('physical',this.value)"></label><output id="ss-layout-dimension"></output>${[['x','X (px)',-150,150],['y','Y (px)',-150,150],['size','Texte (px)',8,36],['width','Largeur (px)',24,200]].map(([id,title,min,max])=>`<label>${title}<input style="width:75px" id="ss-layout-${id}" type="number" min="${min}" max="${max}" onchange="ssLayoutSet('${id}',this.value)"></label>`).join('')}<button onclick="ssLayoutReset()">Rétablir cet élément</button><div id="ss-layout-alert" role="status" style="width:100%;font-size:12px;color:#8b3d16"></div></fieldset><style>.ss-layout-editing [data-layout]{cursor:pointer}.ss-layout-selected{outline:1px dashed #1675c9!important}.ss-layout-handle{position:absolute;z-index:150;width:12px;height:12px;background:#1675c9;border:2px solid white;box-sizing:border-box;touch-action:none}.ss-layout-move{left:0;top:0;cursor:move}.ss-layout-resize{right:0;bottom:0;cursor:nwse-resize}@media print{.ss-layout-handle{display:none!important}.ss-layout-selected{outline:none!important}}</style>`;
    return panel.replace('<button onclick="ssLayoutReset()">','<button onclick="ssLayoutApply()">Appliquer les dimensions</button><button onclick="ssLayoutReset()">').replace('<output id="ss-layout-dimension">',`<label><input type="checkbox" ${physicalPreview?'checked':''} onchange="ssLayoutPhysical(this.checked)"> Aperçu au format imprimé</label><output id="ss-layout-dimension">`)+html;
  };
  document.addEventListener('click',event=>{const node=event.target.closest('#ss-card-studio [data-layout]');if(editing&&node&&!event.target.dataset.handle){selected=node.dataset.layout;ssRenderPreview();}});
  document.addEventListener('pointerdown',event=>{
    const handle=event.target.closest('[data-handle]');if(!editing||!handle)return;
    const node=handle.closest('[data-layout]'),type=typeOf(node.closest('.art')),id=node.dataset.layout;
    const saved=settings.elements[type][id]||{};
    gesture={node,type,id,mode:handle.dataset.handle,x:event.clientX,y:event.clientY,value:{...saved},size:parseFloat(getComputedStyle(node).fontSize),width:parseFloat(getComputedStyle(node).width),scale:node.closest('.art').getBoundingClientRect().width/(type==='badge'?340:560)};
    handle.setPointerCapture(event.pointerId);event.preventDefault();
  });
  document.addEventListener('pointermove',event=>{
    if(!gesture)return;const g=gesture,dx=(event.clientX-g.x)/g.scale,dy=(event.clientY-g.y)/g.scale;
    const v=settings.elements[g.type][g.id]||={};
    if(g.mode==='move'){v.x=Math.max(-150,Math.min(150,(g.value.x||0)+dx));v.y=Math.max(-150,Math.min(150,(g.value.y||0)+dy));g.node.style.translate=`${v.x}px ${v.y}px`;}
    else if(['photo','schoolLogo','brandLogo'].includes(g.id)){v.width=Math.max(24,Math.min(200,g.width+dx));g.node.style.width=v.width+'px';}
    else{v.size=Math.max(8,Math.min(36,g.size+dy/4));g.node.style.setProperty('font-size',v.size+'px','important');}
    event.preventDefault();
  });
  const finish=()=>{if(!gesture)return;gesture=null;save();ssRenderPreview();};
  document.addEventListener('pointerup',finish);document.addEventListener('pointercancel',finish);
})();


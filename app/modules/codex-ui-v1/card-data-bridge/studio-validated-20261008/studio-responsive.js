(() => {
  function fitPreview() {
    const studio=document.getElementById('ss-card-studio');if(!studio)return;
    const available=Math.max(1,studio.clientWidth-20);
    studio.querySelectorAll('.art').forEach(card=>{
      const host=card.closest('[data-print-face]');if(!host)return;
      const physical=host.classList.contains('ss-preview-physical');
      const width=card.classList.contains('carte')?560:340,height=card.classList.contains('carte')?353:540;
      const scale=Math.min(1,available/width);
      host.classList.add('ss-responsive-face');
      host.style.setProperty('--preview-width',width*scale+'px');host.style.setProperty('--preview-height',height*scale+'px');host.style.setProperty('--preview-scale',scale);
      const wrap=host.closest('.ss-badge-wrap,.ss-carte-wrap');
      if(wrap){wrap.style.width=physical?host.style.getPropertyValue('--print-width'):width*scale+'px';wrap.style.maxWidth='100%';}
    });
  }
  const preview=window.ssRenderPreview;
  window.ssRenderPreview=function(...args){const result=preview.apply(this,args);fitPreview();return result;};
  window.addEventListener('resize',fitPreview);
  document.addEventListener('DOMContentLoaded',()=>{
    const root=document.getElementById('PC');if(root&&window.ResizeObserver)new ResizeObserver(fitPreview).observe(root);
    fitPreview();
  });
})();

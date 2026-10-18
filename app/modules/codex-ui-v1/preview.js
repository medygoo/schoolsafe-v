(function () {
  'use strict';
  var catalog=window.SchoolSafeCodexCatalog;
  var active={demo:true,role:'admin',schoolName:'Ecole de demonstration',revision:1};
  var select=document.getElementById('previewRole');
  var resize=new ResizeObserver(function(){document.getElementById('codexDashboardPreview').style.setProperty('--sscx-preview-offset',document.querySelector('.sscx-preview-controls').offsetHeight+'px');});
  resize.observe(document.querySelector('.sscx-preview-controls'));
  select.innerHTML=Object.keys(catalog.roles).map(function(key){return '<option value="'+key+'">'+catalog.roles[key].label+'</option>';}).join('');
  var fixtures=window.SchoolSafeCodexDemo;
  window.codexPreview=window.SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{demo:true,schoolWorkflows:true,featureScreens:true,getSession:function(){return active;},demoData:fixtures});
  select.addEventListener('change',function(){active={demo:true,role:select.value,schoolName:'Ecole de demonstration',revision:active.revision+1};window.codexPreview.refreshSession();});
  document.getElementById('previewScenario').addEventListener('change',function(event){fixtures.setScenario(event.target.value);active.revision++;window.codexPreview.refreshSession();});
})();

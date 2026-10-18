/* Opt-in, demo-only presentations. No API, storage, permission or business engine. */
(function(root){
  'use strict';
  var base=document.currentScript.src;
  var assets={logo:new URL('../../schoolsafe-logo.png',base).href,jaspe:new URL('../../assets/jaspe2d/attente-v13/adossee-detouree.png',base).href};
  var layoutLabels={register:'Registre',agenda:'Agenda',cases:'Dossiers à examiner',reports:'Rapports disponibles',documents:'Documents',statement:'Rubriques',messages:'Échanges',timeline:'Historique',equipment:'Équipements et services',settings:'Configuration',gallery:'Ressources graphiques'};
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function icon(n){return '<i data-lucide="'+esc(n)+'" aria-hidden="true"></i>';}
  function button(label,attrs,n){return '<button type="button" '+attrs+'>'+icon(n||'arrow-right')+'<span>'+esc(label)+'</span></button>';}
  function tool(label,attrs,n){return '<button type="button" class="sscx-icon" aria-label="'+esc(label)+'" title="'+esc(label)+'" '+attrs+'>'+icon(n)+'</button>';}
  function create(host,bridge){
    var catalog=root.SchoolSafeCodexFeatureCatalog,action=null,revision=0,controller=null,state;
    function reset(){revision++;controller?.abort();controller=null;action=null;state={open:false,busy:false,rows:[],error:'',query:'',filter:'',sort:'',page:0,selected:'',mode:'list',values:{},errors:{}};}
    reset();
    function enabled(a){return !!a&&bridge.featureScreens===true&&bridge.demo()&&!!catalog.routes[a.id]&&bridge.actions().some(function(x){return x.id===a.id;});}
    function def(){return catalog.definitions[catalog.routes[action.id].key];}
    function active(){return enabled(action)&&state.open;}
    function direct(a){return enabled(a)&&!catalog.routes[a.id].entry;}
    function render(focus){bridge.render();bridge.icons();if(focus)host.querySelector(focus)?.focus();}
    function projected(data,d){
      if(!data||!Array.isArray(data.rows)||data.rows.length>1000)throw new Error('Invalid fixture');
      var ids=new Set();return data.rows.map(function(row){
        if(!row||typeof row.id!=='string'||!row.id||row.id.length>160||ids.has(row.id))throw new Error('Invalid example id');ids.add(row.id);
        var clean={id:row.id};d.fields.forEach(function(f){if(row[f.key]!=null){if(!['string','number','boolean'].includes(typeof row[f.key]))throw new Error('Invalid fixture value');clean[f.key]=String(row[f.key]).slice(0,4000);}});return clean;
      });
    }
    async function load(a){
      controller?.abort();controller=new AbortController();var token=++revision;action=a;state.open=true;state.busy=true;state.rows=[];state.error='';state.selected='';state.mode='list';state.values={};state.errors={};render();
      try{
        if(typeof bridge.demoData?.featureScreen!=='function')throw new Error('No fixture provider');
        var data=await bridge.demoData.featureScreen({key:catalog.routes[a.id].key,actionId:a.id,session:bridge.session(),signal:controller.signal});
        if(token!==revision||!enabled(a))return;
        state.rows=projected(data,def());state.busy=false;render('[data-feature-heading]');
      }catch(error){if(token!==revision||!enabled(a))return;state.rows=[];state.busy=false;state.error=error.denied?'Accès refusé dans ce scénario.':'Chargement des exemples impossible.';render('[data-feature-state]');}
    }
    function activate(a){reset();action=a;if(direct(a))return load(a);}
    function entry(a){return enabled(a)&&catalog.routes[a.id].entry?'<div class="sscx-toolbar">'+button('Ouvrir l’espace '+(catalog.definitions[catalog.routes[a.id].key].form.length?'de préparation':'de consultation'),'data-feature-command="start"','panels-top-left')+'</div>':'';}
    function chosen(){return state.rows.find(function(row){return row.id===state.selected;});}
    function facet(d){return d.fields.find(function(f){return f.key==='status';})||d.fields[1];}
    function factList(row,fields){return '<dl class="sscx-feature-facts">'+fields.map(function(f){return '<div data-feature-field="'+f.key+'"><dt>'+esc(f.label)+'</dt><dd>'+esc(row[f.key]||'Non renseigné')+'</dd></div>';}).join('')+'</dl>';}
    function openButton(row){return tool('Consulter '+row.name,'data-feature-record="'+esc(row.id)+'"','arrow-up-right');}
    function list(d,rows){
      if(d.layout==='register'||d.layout==='statement')return '<div class="sscx-table-scroll" role="region" tabindex="0" aria-label="'+esc(action.label)+'"><table><caption>'+esc(action.label)+' · Exemples fictifs</caption><thead><tr>'+d.fields.map(function(f){return '<th scope="col">'+esc(f.label)+'</th>';}).join('')+'<th scope="col">Détail</th></tr></thead><tbody>'+rows.map(function(row){return '<tr>'+d.fields.map(function(f){return '<td>'+esc(row[f.key]||'Non renseigné')+'</td>';}).join('')+'<td>'+openButton(row)+'</td></tr>';}).join('')+'</tbody></table></div>';
      return '<ul class="sscx-feature-list sscx-feature-list--'+d.layout+'">'+rows.map(function(row){
        var meta=d.fields.filter(function(f){return f.key!=='name';}),long=meta.find(function(f){return ['content','objective','reason','note'].includes(f.key);});
        var image=d.layout==='gallery'?'<img src="'+assets[row.name==='JASPE'?'jaspe':'logo']+'" alt="'+esc(row.name)+'" width="400" height="260">':'';
        var marker=['agenda','timeline'].includes(d.layout)?'<div class="sscx-feature-date">'+icon(d.icon)+'<span>'+esc(row.day||row.date||row.from||row.type||'Exemple')+'</span>'+(row.time?'<strong>'+esc(row.time)+'</strong>':'')+'</div>':'';
        return '<li>'+image+marker+'<div class="sscx-feature-item-body"><h3>'+esc(row.name)+'</h3>'+factList(row,meta.filter(function(f){return f!==long;}))+(long?'<p class="sscx-feature-excerpt">'+esc(row[long.key])+'</p>':'')+'</div>'+openButton(row)+'</li>';
      }).join('')+'</ul>';
    }
    function filters(d){
      var f=facet(d),values=Array.from(new Set(state.rows.map(function(row){return row[f.key];}).filter(Boolean)));
      return '<div class="sscx-feature-filters"><label for="feature-search">Recherche<input type="search" id="feature-search" data-feature-search value="'+esc(state.query)+'"></label><label for="feature-filter">'+esc(f.label)+'<select id="feature-filter" data-feature-filter><option value="">Tout afficher</option>'+values.map(function(v){return '<option '+(v===state.filter?'selected':'')+'>'+esc(v)+'</option>';}).join('')+'</select></label><label for="feature-sort">Trier par<select id="feature-sort" data-feature-sort><option value="">Ordre initial</option>'+d.fields.map(function(f){return '<option value="'+f.key+'" '+(state.sort===f.key?'selected':'')+'>'+esc(f.label)+'</option>';}).join('')+'</select></label></div>';
    }
    function listHtml(d){
      var f=facet(d),rows=state.rows.filter(function(row){return (!state.filter||row[f.key]===state.filter)&&d.fields.some(function(field){return String(row[field.key]||'').toLocaleLowerCase('fr').includes(state.query.toLocaleLowerCase('fr'));});});
      if(state.sort)rows.sort(function(a,b){return String(a[state.sort]||'').localeCompare(String(b[state.sort]||''),'fr',{numeric:true});});
      var pages=Math.max(1,Math.ceil(rows.length/6));state.page=Math.min(state.page,pages-1);
      var content=rows.length?list(d,rows.slice(state.page*6,state.page*6+6)):'<div class="sscx-feature-empty" role="status">'+icon('search-x')+'<h3>'+(!state.rows.length?'Aucun élément disponible':'Aucun résultat')+'</h3><p>'+(!state.rows.length?'Aucun contenu transmis pour cette vue.':'Aucun exemple ne correspond aux filtres.')+'</p></div>';
      return filters(d)+'<div class="sscx-feature-results"><span>'+rows.length+' exemple(s) affichable(s)</span>'+((state.query||state.filter)?button('Effacer les filtres','data-feature-command="clear"','filter-x'):'')+'</div>'+content+'<div class="sscx-pagination">'+tool('Page précédente','data-feature-page="-1" '+(state.page===0?'disabled':''),'chevron-left')+'<span>Page '+(state.page+1)+' / '+pages+'</span>'+tool('Page suivante','data-feature-page="1" '+(state.page+1===pages?'disabled':''),'chevron-right')+'</div>';
    }
    function detailHtml(d){var row=chosen();if(!row)return '<p role="status">Élément indisponible.</p>';return button('Retour aux résultats','data-feature-command="list"','arrow-left')+'<article class="sscx-feature-detail"><header><span class="sscx-badge">Exemple fictif</span><h3 tabindex="-1" data-feature-detail-heading>'+esc(row.name)+'</h3></header>'+factList(row,d.fields.filter(function(f){return f.key!=='name';}))+'</article>';}
    function input(f){
      var value=state.values[f.key]||'',id='feature-input-'+f.key,error=state.errors[f.key];
      var attrs='id="'+id+'" name="'+f.key+'" '+(f.required?'required ':'')+(error?'aria-invalid="true" aria-describedby="'+id+'-error" ':'');
      var control=f.type==='textarea'?'<textarea '+attrs+' rows="5" maxlength="2000">'+esc(value)+'</textarea>':f.type==='select'?'<select '+attrs+'><option value="">Sélectionner</option>'+f.choices.map(function(v){return '<option '+(value===v?'selected':'')+'>'+esc(v)+'</option>';}).join('')+'</select>':'<input '+attrs+' type="'+f.type+'" '+(f.type==='number'?'min="1" step="1"':'maxlength="200"')+' value="'+esc(value)+'" autocomplete="off">';
      return '<div class="sscx-feature-field '+(f.type==='textarea'?'sscx-feature-field--wide':'')+'"><label for="'+id+'">'+esc(f.label)+(f.required?' <span aria-hidden="true">*</span>':'')+'</label>'+control+(error?'<p class="sscx-field-error" id="'+id+'-error">'+esc(error)+'</p>':'')+'</div>';
    }
    function draftHtml(d){
      var fields=d.fields.filter(function(f){return d.form.includes(f.key);}),bad=fields.filter(function(f){return state.errors[f.key];});
      if(state.mode==='review')return '<section class="sscx-feature-review"><h3 tabindex="-1" data-feature-review-heading>Relecture du brouillon local</h3><p role="status">Non enregistré, non transmis. La validation métier n’a pas été effectuée.</p>'+factList(state.values,fields)+'<div class="sscx-toolbar">'+button('Modifier','data-feature-command="edit"','square-pen')+button('Fermer le brouillon','data-feature-command="cancel"','x')+'</div></section>';
      return '<form data-feature-form class="sscx-feature-form" novalidate><h3 tabindex="-1" data-feature-draft-heading>Brouillon local</h3><p class="sscx-feature-boundary">Saisie temporaire. Aucun enregistrement ni envoi; effacée en quittant cet espace.</p>'+(bad.length?'<div role="alert" tabindex="-1" class="sscx-feature-errors" aria-label="Champs à corriger"><h3>Champs à corriger</h3><ul>'+bad.map(function(f){return '<li><button type="button" data-feature-error="'+f.key+'">'+esc(f.label)+': '+esc(state.errors[f.key])+'</button></li>';}).join('')+'</ul></div>':'')+'<div class="sscx-feature-grid">'+fields.map(input).join('')+'</div><div class="sscx-toolbar"><button type="submit">'+icon('check')+'<span>Relire le brouillon</span></button>'+button('Fermer','data-feature-command="cancel"','x')+'</div></form>';
    }
    function html(){
      if(!active())return '';var d=def(),body;
      if(state.busy)body='<p role="status">Chargement des exemples…</p>';
      else if(state.error)body='<div class="sscx-feature-state" role="alert" tabindex="-1" data-feature-state><p>'+esc(state.error)+'</p>'+button('Réessayer','data-feature-command="refresh"','refresh-cw')+'</div>';
      else body=state.mode==='detail'?detailHtml(d):['draft','review'].includes(state.mode)?draftHtml(d):listHtml(d);
      return '<section class="sscx-section sscx-feature-screen" data-feature-screen="'+d.key+'" data-feature-layout="'+d.layout+'"><div class="sscx-section-heading"><h2 tabindex="-1" data-feature-heading>'+icon(d.icon)+'<span>'+esc(layoutLabels[d.layout])+'</span></h2><div class="sscx-toolbar">'+(catalog.routes[action.id].entry?button('Retour à la liste','data-feature-command="close"','arrow-left'):'')+tool('Actualiser les exemples','data-feature-command="refresh" '+(state.busy?'disabled':''),'refresh-cw')+'</div></div><p class="sscx-feature-boundary">Démonstration · '+esc(d.note)+'</p>'+(action.reason?'<p class="sscx-feature-boundary">'+esc(action.reason)+'</p>':'')+(!state.busy&&!state.error&&d.form.length&&state.mode==='list'?'<div class="sscx-toolbar">'+button('Préparer un brouillon local','data-feature-command="draft"','square-pen')+'</div>':'')+body+(action.type==='existing'?'<div class="sscx-feature-existing sscx-toolbar">'+button('Ouvrir la fonction existante','data-command="open-existing"','arrow-up-right')+'</div>':'')+'</section>';
    }
    function onClick(b){
      if(b.dataset.featureCommand==='start'&&enabled(action)){load(action);return true;}
      if(!active())return false;
      if(b.dataset.featureRecord){if(state.rows.some(function(row){return row.id===b.dataset.featureRecord;})){state.selected=b.dataset.featureRecord;state.mode='detail';render('[data-feature-detail-heading]');}return true;}
      if(b.dataset.featureError){host.querySelector('#feature-input-'+b.dataset.featureError)?.focus();return true;}
      if(b.dataset.featurePage){state.page=Math.max(0,state.page+Number(b.dataset.featurePage));render('[data-feature-page="'+b.dataset.featurePage+'"]');return true;}
      switch(b.dataset.featureCommand){
        case 'refresh':load(action);return true;
        case 'close':var a=action;reset();action=a;render('[data-feature-command="start"]');return true;
        case 'list':state.mode='list';render('[data-feature-record="'+state.selected+'"]');return true;
        case 'clear':state.query='';state.filter='';state.page=0;render('[data-feature-search]');return true;
        case 'draft':if(!def().form.length)return true;state.mode='draft';state.values={};state.errors={};render('[data-feature-draft-heading]');return true;
        case 'edit':state.mode='draft';render('[data-feature-draft-heading]');return true;
        case 'cancel':state.mode='list';state.values={};state.errors={};render('[data-feature-command="draft"]');return true;
      }
      return false;
    }
    function issue(el){if(el.required&&!el.value.trim())return 'Ce champ est requis.';if(!el.validity.valid)return el.type==='email'?'Saisir une adresse e-mail valide.':el.type==='number'?'Saisir un entier supérieur ou égal à 1.':'Vérifier la valeur.';return '';}
    function onInput(el){
      if(!active())return false;
      if(el.matches('[data-feature-search]')){var pos=el.selectionStart;state.query=el.value;state.page=0;render('[data-feature-search]');host.querySelector('[data-feature-search]')?.setSelectionRange(pos,pos);return true;}
      if(el.closest('[data-feature-form]')&&def().form.includes(el.name)){state.values[el.name]=el.value;return true;}return false;
    }
    function onChange(el){if(!active())return false;if(el.matches('[data-feature-filter],[data-feature-sort]')){state[el.matches('[data-feature-filter]')?'filter':'sort']=el.value;state.page=0;render(el.matches('[data-feature-filter]')?'[data-feature-filter]':'[data-feature-sort]');return true;}return onInput(el);}
    function onSubmit(form){
      if(!active()||!form.matches('[data-feature-form]'))return false;state.errors={};
      Array.from(form.querySelectorAll('input,select,textarea')).forEach(function(el){state.values[el.name]=el.value;var error=issue(el);if(error)state.errors[el.name]=error;});
      if(state.values.from&&state.values.to&&state.values.to<state.values.from)state.errors.to='La fin doit être postérieure ou égale au début.';
      if(Object.keys(state.errors).length){render('.sscx-feature-errors');return true;}
      state.mode='review';render('[data-feature-review-heading]');return true;
    }
    return {activate:activate,direct:direct,entry:entry,html:html,reset:reset,onClick:onClick,onInput:onInput,onChange:onChange,onSubmit:onSubmit};
  }
  root.SchoolSafeCodexFeatureScreens={create:create};
})(window);

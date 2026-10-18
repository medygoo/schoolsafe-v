/* Render only fields projected for the current authorized tab. No list-row fallback. */
(function (root) {
  'use strict';
  var kinds={students:['Dossier \u00e9l\u00e8ve','\u00c9l\u00e8ve','Scolarit\u00e9'],classes:['Fiche classe','Classe','Enseignement'],staff:['Fiche du personnel','Personne','Affectation'],attendance:['Pr\u00e9sences','\u00c9l\u00e8ve','Classe'],family:['Fiche familiale','Personne','Enfant et lien familial'],subjects:['Fiche mati\u00e8re','Mati\u00e8re','Organisation'],assignments:['Devoir','Titre','Classe et mati\u00e8re'],grades:['R\u00e9sultats','\u00c9l\u00e8ve','\u00c9valuation'],lessons:['Pr\u00e9paration de cours','S\u00e9ance','Organisation']};
  var copy={'Dossiers des eleves':'Dossiers des \u00e9l\u00e8ves','Eleve':'\u00c9l\u00e8ve','Etat':'\u00c9tat','Reference':'R\u00e9f\u00e9rence','Annee scolaire':'Ann\u00e9e scolaire','Contact autorise':'Contact autoris\u00e9','Matiere':'Mati\u00e8re','Matieres':'Mati\u00e8res','Echeance':'\u00c9ch\u00e9ance','Evaluation':'\u00c9valuation','Evaluations et resultats':'\u00c9valuations et r\u00e9sultats','Note publiee':'Note publi\u00e9e','Deroulement':'D\u00e9roulement','Perimetre':'P\u00e9rim\u00e8tre','Presences et passages':'Pr\u00e9sences et passages','Familles et personnes autorisees':'Familles et personnes autoris\u00e9es','Preparation pedagogique':'Pr\u00e9paration p\u00e9dagogique','Scolarite':'Scolarit\u00e9','Identite':'Identit\u00e9','Presence':'Pr\u00e9sence','Titre de la seance':'Titre de la s\u00e9ance','Retour a la liste':'Retour \u00e0 la liste','Preparer un brouillon':'Pr\u00e9parer un brouillon'};
  Object.assign(kinds,{documents:['Fiche documentaire','Document','Classement'],years:['Organisation scolaire','Intitule','Periode'],alerts:['Fiche de suivi','Signalement','Classification'],overview:['Fiche indicateur','Indicateur','Source'],access:['Fiche des acces','Profil','Poste et perimetre']});
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function supports(kind){return Object.hasOwn(kinds,kind);}
  function label(text){return Object.hasOwn(copy,text)?copy[text]:text;}
  function detail(kind,fields,labels){
    if(!supports(kind)||!fields||!Object.keys(fields).length)return '';
    var profile=kinds[kind],used=new Set();
    function field(key,title){used.add(key);return '<div data-detail-field="'+esc(key)+'"><dt>'+esc(title||(kind==='students'&&key==='reference'?'Matricule':label(labels[key]||key)))+'</dt><dd>'+esc(fields[key]===''?'Non renseign\u00e9':fields[key])+'</dd></div>';}
    function group(title,keys,wide){var present=keys.filter(function(key){return Object.hasOwn(fields,key)&&!used.has(key);});return present.length?'<section class="sscx-detail-group '+(wide?'sscx-detail-prose':'')+'"><h3>'+esc(title)+'</h3><dl>'+present.map(function(key){return field(key);}).join('')+'</dl></section>':'';}
    var identity=(Object.hasOwn(fields,'name')?field('name',profile[1]):'')+(Object.hasOwn(fields,'status')?field('status','\u00c9tat'):'');
    var header=identity?'<header class="sscx-detail-identity" aria-label="'+esc(profile[0])+'"><dl>'+identity+'</dl></header>':'';
    var body=group(profile[2],['class','reference','year','teacher','subject','coefficient','post','scope','student','relationship']);
    body+=group('Famille et contact',['guardian','contact']);
    body+=group('Dates',['date','due']);
    body+=group('Documents et suivi',['document','photo_review','request_status']);
    body+=group('Contenu et observations',['instruction','correction','objective','content','comment','reason'],true);
    body+=group('Informations compl\u00e9mentaires',Object.keys(fields));
    return '<div class="sscx-data-list sscx-detail-sheet" data-detail-kind="'+esc(kind)+'">'+header+'<div class="sscx-detail-groups">'+body+'</div></div>';
  }
  root.SchoolSafeCodexRecordPresentation={supports:supports,label:label,detail:detail};
})(window);

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const menus = JSON.parse(fs.readFileSync(path.join(__dirname, 'LOT0_MENUS.json'), 'utf8'));
const branches = [
 ['pilotage','Pilotage','layout-dashboard'],['school','Ecole','school'],['pedagogy','Pedagogie','book-open-check'],
 ['security','Securite et controle','shield-check'],['finance','Finance','wallet-cards'],['feeControl','Controle des frais','badge-check'],
 ['accounting','Comptabilite','landmark'],['people','Personnel','contact-round'],['inventory','Stock / Inventaire','package-search'],
 ['communication','Communication','messages-square'],['care','Vie et bien-etre','heart-pulse'],['administration','Administration','folder-cog'],['reports','Controle et rapports','chart-no-axes-combined']
].map(([id,label,icon])=>({id,label,icon}));
const actions=[], roles={};
for(const row of menus){
 if(/palmar[eè]s/i.test(row.label)||['6e A','5e A'].includes(row.label))continue;
 let action=actions.find(a=>a.branch===row.branch && a.label===row.label);
 if(!action){
  action={id:'rosario-'+String(actions.length+1).padStart(3,'0'),branch:row.branch,label:row.label,group:row.group,type:'existing',legacy:{branch:row.branch,action:row.label},source:row.source,permission:null};
  if(/Bulletins/.test(row.label))Object.assign(action,{type:'unavailable',reason:'Le moteur de bulletin officiel est indisponible.'});
  actions.push(action);
 }
 roles[row.role] ||= {label:row.roleLabel,scope:row.scope,actions:[]};
 if(!roles[row.role].actions.includes(action.id))roles[row.role].actions.push(action.id);
}
const reads=[
 ['presence-teacher','pedagogy','Ma journee','Synthese de presence','teacherBoard','school.student.read',['teacher','pedagogy']],
 ['presence-school','pilotage','Presence de l\'ecole','Vue executive','schoolBoard','pilotage.dashboard.read',['admin','school_head']],
 ['presence-student','school','Presence d\'un eleve','Suivi','onSiteStudent','school.student.read',['admin','school_head','teacher','parent','admissions']],
 ['presence-class','pedagogy','Presence d\'une classe','Mes classes','onSiteClass','school.student.read',['admin','school_head','teacher','pedagogy']],
 ['presence-school-count','pilotage','Eleves sur site','Indicateurs','onSiteSchool','pilotage.dashboard.read',['admin','school_head']],
 ['scan-history','security','Trace des controles','Historique','scanAudit','security.events.read',['admin','guard','school_head']]
];
for(const [id,branch,label,group,reader,permission,profiles] of reads){
 actions.push({id,branch,label,group,type:'read',reader,permission,source:'server/src/scancontrol/routes.ts:133-154'});
 for(const role of profiles)roles[role].actions.unshift(id);
}
actions.push({id:'scan-fees','branch':'feeControl',label:'Scanner les frais',group:'Consultation',type:'existing',legacy:{branch:'security',action:'Scanner un QR',mode:'fees'},permission:'finance.control.scan',capability:'SCAN_FEES',source:'app/modules/security/scan-control.js'});
for(const role of ['admin','finance','cashier'])roles[role].actions.push('scan-fees');
const catalog={version:1,branches,actions,roles,excluded:['Palmares: hors LOT 1','6e A et 5e A: exemples fixes remplaces par liste des classes autorisees dans presence-class'],notice:'Les profils et groupes sont des metadonnees de presentation, jamais une attribution de droits.'};
fs.writeFileSync(path.join(root,'app/modules/codex-ui-v1/catalog.js'),'(function(root){"use strict"; root.SchoolSafeCodexCatalog = '+JSON.stringify(catalog,null,2)+'; })(window);\n');
fs.writeFileSync(path.join(__dirname,'LOT1_BUTTON_SCREEN_MAPPING.json'),JSON.stringify(catalog,null,2)+'\n');
console.log(actions.length+' destinations, '+Object.keys(roles).length+' postes, '+branches.length+' branches');

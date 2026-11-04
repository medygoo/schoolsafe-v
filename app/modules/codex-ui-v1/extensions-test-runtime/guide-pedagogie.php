<?php
// Mode d'emploi Pedagogie (04/11, demande du proprietaire) : la procedure d'utilisation de chaque profil,
// dans l'ordre reel du travail (debut d'annee, chaque jour, chaque periode, fin d'annee). Lecture seule.
// Chaque compte voit d'abord son profil ; la direction voit tous les profils pour former son equipe.
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'||$_GET){http_response_code(400);exit('Requête invalide.');}
chdir(__DIR__);$_REQUEST=['modname'=>'misc/Portal.php','modfunc'=>''];
$level=ob_get_level();ob_start();require_once __DIR__.'/Warehouse.php';
require_once __DIR__.'/ProgramFunctions/SchoolSafePedagogyNav.fnc.php';
$allowed=(int)User('STAFF_ID')>0||(int)($_SESSION['STUDENT_ID']??0)>0;
$role=$allowed?SchoolSafePedagogyRole():'';
while(ob_get_level()>$level)ob_end_clean();
if(!$allowed||$role===''){http_response_code(403);exit('Connectez-vous pour lire le mode d’emploi.');}
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$order=['direction'=>'Direction','enseignant'=>'Enseignant et titulaire','parent'=>'Parent','eleve'=>'Élève'];
// La direction forme son equipe : elle voit tous les profils. Les autres comptes ne voient que le leur.
$shown=$role==='direction'?array_keys($order):[$role];
$guide=[
'direction'=>[
 ['Au début de l’année (une fois)',[
  ['Préparer l’année','preparer-annee.php','Suivez les étapes dans l’ordre : périodes de notes (3 trimestres de 2 périodes), barème de l’école, classes, élèves placés dans leur classe, enseignants attribués.'],
  ['Branches et maxima','branches-classe.php','Pour chaque niveau, vérifiez la liste des branches avec un ancien bulletin de l’école (domaine, maxima de période ; l’examen vaut le double sauf indication). Enregistrez la liste. Puis, pour chaque classe : choisissez la liste, le titulaire (notes en français) et, si la classe est bilingue, le professeur d’anglais → « Aperçu » → « Créer et appliquer ». Choisissez aussi « moyenne » ou « somme » pour le français et l’anglais.'],
  ['Papier de l’école','papier-ecole.php','Vérifiez le logo, le ministère, la province, la commune, le code de l’école et le N° ID (ils figurent sur le bulletin), la disposition des documents, et les envois automatiques (devoirs, notes : tout de suite ou le soir).'],
  ['Fiches des élèves','Modules.php?modname=Students/Student.php','Complétez le sexe, la date et le lieu de naissance, le matricule (N° permanent) : le bulletin les reprend.'],
 ]],
 ['Chaque semaine',[
  ['Suivi pédagogique','suivi-pedagogique.php','Regardez « À relancer » (appels non faits, cahier de textes vide, copies non notées) et « Élèves à surveiller » (absences, retards, notes basses, moyenne en baisse). Décidez : appeler les parents, convoquer, rattrapage. L’onglet « Journal des notes » montre toute note ajoutée, changée ou retirée, avec son auteur.'],
  ['Rattrapage','rattrapage.php','Validez ou refusez les dossiers proposés, fixez le tarif, suivez les séances.'],
  ['Convocations et courriers','courriers.php','Choisissez le modèle et l’élève (ou la classe). « Rédiger avec JASPE » propose un texte : relisez-le, complétez les « … », puis enregistrez et envoyez. Le courrier part aux parents et reste au registre.'],
  ['Archives','archives.php','Photographiez ou déposez chaque document reçu (assurance, facture, preuve de paiement…). Le texte est lu automatiquement : retrouvez-le ensuite par n’importe quel mot.'],
 ]],
 ['À la fin de chaque trimestre',[
  ['Synthèse du bulletin','synthese-bulletin.php','Choisissez la classe et le trimestre. Vérifiez les notes manquantes, les totaux et les places. Quand le titulaire a proposé : « Valider (verrouiller) ». Chaque parent est prévenu ; le bulletin s’ouvre quand les frais dus sont payés (réglable), ou par « Ouvrir malgré les frais » avec une raison. « Rouvrir » permet une correction.'],
 ]],
 ['À la fin de l’année',[
  ['Synthèse du bulletin — « Année et décision »','synthese-bulletin.php','Le titulaire propose la décision (passe, double, repêchage…) et le mot du titulaire ; vous validez. Le bulletin RDC complet et le dossier de l’année restent pour toujours dans l’espace des parents.'],
 ]],
],
'enseignant'=>[
 ['Chaque jour',[
  ['Ma journée de classe','ma-journee.php','Faites l’appel : il est pré-rempli par le scan d’entrée ; « Tous présents » puis corrigez les absents et les retards → « Enregistrer l’appel ». Remplissez le cahier de textes (fait aujourd’hui, à faire). Sans réseau, l’appel est gardé sur le téléphone et part tout seul au retour du réseau.'],
  ['Devoirs et travaux','devoirs-travaux.php','« Nouveau devoir » : choisissez la classe (la branche), le type (devoir, interrogation, examen), le barème, les dates et les consignes. Les parents le reçoivent automatiquement, au nom de leur enfant.'],
  ['Corriger','devoirs-travaux.php','« Corriger » : notez chaque élève et ajoutez une appréciation ; écrivez le corrigé type ; prenez en photo chaque copie corrigée (📷). Chaque parent reçoit la note avec la correction ; la colonne « Vu » montre qui l’a lue.'],
 ]],
 ['À la fin de chaque période',[
  ['Notes du bulletin','notes-bulletin.php','Choisissez la classe et la colonne (période ou examen). En gris : la moyenne de vos devoirs ramenée au maxima ; « Reprendre les suggestions » les recopie ; corrigez si besoin → « Enregistrer ». Chaque enseignant note dans sa langue (français ou anglais).'],
 ]],
 ['Si vous êtes titulaire',[
  ['Synthèse du bulletin','synthese-bulletin.php','Vérifiez les notes manquantes de toute la classe (relancez les collègues). Remplissez l’application et la conduite → « Enregistrer » → « Proposer à la direction ». En fin d’année : « Année et décision », la décision et le mot du titulaire (« Mots proposés par JASPE » donne un brouillon à relire).'],
 ]],
 ['Aide',[
  ['JASPE','Modules.php?modname=SchoolSafe_Jaspe/Assistant.php','Écrivez ou parlez (🎤) à JASPE pour préparer un devoir, un exercice ou un message. Elle propose, vous décidez ; les noms des élèves ne lui sont jamais envoyés.'],
 ]],
],
'parent'=>[
 ['Chaque jour',[
  ['Les messages de l’école','parent-notifications-view.php','Vous recevez sur le fil, par e-mail ou WhatsApp : chaque devoir (fiche à imprimer au nom de votre enfant), chaque note avec la correction, les absences et les courriers. Sans internet, les alertes importantes arrivent par SMS.'],
  ['Travail de mon enfant','travail-enfant.php','En un coup d’œil : devoirs à faire, notes récentes, présence, cahier de textes. Choisissez l’enfant en haut si vous en avez plusieurs.'],
  ['Une note','travail-enfant.php','« Voir la correction » : la note, l’appréciation, le corrigé, la photo de la copie corrigée. Touchez « J’ai vu cette note » (remplace la signature du cahier). « Expliquer » : en français simple, lingala ou swahili.'],
 ]],
 ['Quand vous voulez',[
  ['Dossier de l’année','dossier-annee.php','Tous les travaux de l’année, classés par trimestre et par matière, avec le bilan. Téléchargez-le en PDF pour le garder.'],
  ['Signaler une absence','signaler-absence.php','Prévenez l’école d’une absence ou d’un retard.'],
 ]],
 ['À la fin du trimestre',[
  ['Bulletin','bulletin.php','Le bulletin s’ouvre quand la direction l’a validé et que les frais dus sont payés. Sinon, la page indique le reste à payer ; les notes de chaque devoir restent toujours visibles.'],
 ]],
 ['Sans connexion',[
  ['Pages gardées','travail-enfant.php','Les dernières pages ouvertes (travail, notes, dossier, bulletin) restent lisibles sans réseau, avec leur date. Elles sont effacées quand vous vous déconnectez.'],
 ]],
],
'eleve'=>[
 ['Chaque jour',[
  ['Mon travail','travail-enfant.php','Vos devoirs à faire, vos notes, votre présence, le cahier de textes de la classe.'],
  ['Rendre un devoir','travail-enfant.php','« Rendre en ligne (photo) » : photographiez votre travail avant la date de remise.'],
  ['Comprendre une note','travail-enfant.php','« Voir la correction » : votre note, l’appréciation, le corrigé (après la date de remise) et votre copie corrigée.'],
 ]],
 ['Quand vous voulez',[
  ['Dossier de l’année','dossier-annee.php','Tous vos travaux de l’année et votre bilan, à garder en PDF.'],
  ['Bulletin','bulletin.php','Votre bulletin, quand il est publié.'],
 ]],
],
];
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mode d’emploi — Pédagogie | SchoolSafe</title><link rel="stylesheet" href="schoolsafe-card-bridge/parent-notifications.css">
<style>.gd-role{scroll-margin-top:120px}.gd-phase{display:grid;gap:10px}.gd-phase h3{margin:6px 0 0;font-size:16px;color:#556777;text-transform:uppercase;letter-spacing:.4px}
.gd-steps{list-style:none;margin:0;padding:0;display:grid;gap:10px;counter-reset:gd}.gd-steps li{counter-increment:gd;display:grid;grid-template-columns:34px 1fr;gap:10px;align-items:start}
.gd-steps li::before{content:counter(gd);display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:#08766b;color:#fff;font-weight:700}
.gd-steps b a{color:#0b4f48}.gd-steps p{margin:2px 0 0}.gd-rules{margin:0;padding-left:18px}.gd-print{justify-self:start}
@media print{.gd-print{display:none}.gd-role{break-inside:avoid-page}}</style>
<?php echo SchoolSafePedagogyStyle(); ?></head>
<body><?php echo SchoolSafePedagogyHeader('guide-pedagogie.php'); ?>
<main><h1>Mode d’emploi — Pédagogie</h1>
<p class="ss-sub">Les étapes de votre profil, dans l’ordre du travail. Le parcours en haut de chaque page reprend ces étapes ; l’étape où vous êtes est en vert.</p>
<?php if(count($shown)>1):?><div class="ss-chips"><?php foreach($shown as $r):?><a href="#<?=$e($r)?>"><?=$e($order[$r])?></a><?php endforeach;?></div><?php endif;?>
<button class="ss-btn light gd-print" type="button" onclick="window.print()">🖨️ Imprimer ce mode d’emploi</button>
<?php foreach($shown as $r):?>
<section class="ss-box gd-role" id="<?=$e($r)?>"><h2><?=$e($order[$r])?></h2>
<?php foreach($guide[$r] as [$phase,$steps]):?><div class="gd-phase"><h3><?=$e($phase)?></h3><ol class="gd-steps">
<?php foreach($steps as [$title,$url,$text]):?><li><div><b><a href="<?=$e($url)?>"><?=$e($title)?></a></b><p><?=$e($text)?></p></div></li><?php endforeach;?>
</ol></div><?php endforeach;?></section>
<?php endforeach;?>
<section class="ss-box"><h2>Règles communes</h2><ul class="gd-rules">
<li>Une information se saisit une seule fois : la fiche de l’école, de l’élève et de la classe est reprise partout.</li>
<li>Les envois aux parents sont automatiques ; les décisions sensibles (bulletin, rattrapage, frais, sanctions) restent humaines.</li>
<li>JASPE propose, l’humain valide ; les noms des élèves ne lui sont jamais envoyés.</li>
<li>Rien n’est effacé : une note changée va au journal des notes, un document retiré garde sa raison, un bulletin validé est verrouillé.</li>
<li>Chaque page vérifie les droits du compte : on ne voit que ce que son profil permet.</li></ul></section>
</main></body></html>

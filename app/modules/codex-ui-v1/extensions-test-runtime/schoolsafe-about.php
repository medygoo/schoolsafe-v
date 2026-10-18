<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
chdir(__DIR__);
$_REQUEST = ['modname' => 'misc/Portal.php', 'modfunc' => ''];
$level = ob_get_level();
ob_start();
require_once __DIR__ . '/Warehouse.php';
$authenticated = User('STAFF_ID') || User('STUDENT_ID');
while (ob_get_level() > $level) ob_end_clean();
if (!$authenticated) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>À propos de SchoolSafe</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fa;color:#172d40;font:16px/1.6 system-ui,sans-serif}header{background:white;border-bottom:1px solid #dbe4ec}header,main{padding:24px max(24px,calc((100% - 960px)/2))}header{display:flex;align-items:center;justify-content:space-between;gap:20px}header img{width:64px;height:64px;object-fit:contain}a{color:#126cac;text-decoration:none}a:focus-visible{outline:3px solid #126cac;outline-offset:4px}h1{font-size:30px;margin:0}h2{font-size:21px;margin:0 0 8px}section{padding:24px 0;border-bottom:1px solid #dbe4ec}p{margin:8px 0}.brand{display:flex;align-items:center;gap:16px}.services{display:grid;grid-template-columns:1fr 1fr;gap:24px}.services article{border-left:3px solid #28a380;padding-left:16px}small{color:#536a7c}@media(max-width:600px){header{align-items:flex-start;flex-wrap:wrap}.services{grid-template-columns:1fr}h1{font-size:25px}}
</style>
</head>
<body>
<header><a class="brand" href="https://shoolsafe.tech/" target="_blank" rel="noopener noreferrer" title="Ouvrir le site officiel SchoolSafe"><img src="schoolsafe-logo.png" alt="SchoolSafe"><strong>SchoolSafe</strong></a><a href="Modules.php?modname=misc/Portal.php">← Tableau de bord</a></header>
<main>
<section><h1>À propos de SchoolSafe</h1><p>SchoolSafe accompagne la vie scolaire en réunissant les informations de l’école, le suivi des élèves et les échanges avec les familles.</p></section>
<section class="services" aria-label="Services SchoolSafe">
<article><h2>Vie scolaire</h2><p>Dossiers des élèves, inscriptions, classes, présences et documents scolaires.</p></article>
<article><h2>Pédagogie</h2><p>Travaux scolaires, cotes, bulletins, palmarès et suivi du rattrapage.</p></article>
<article><h2>Gestion de l’école</h2><p>Personnel, frais scolaires, cantine et tableaux de bord selon les fonctions disponibles.</p></article>
<article><h2>JASPE</h2><p>L’assistante personnalisée de SchoolSafe vous accompagne et vous oriente vers les fonctionnalités accessibles à votre profil.</p></article>
</section>
<section aria-labelledby="watch-title"><h2 id="watch-title">SchoolSafe Watch</h2><p>Une montre connectée dédiée à la sécurité et au suivi des enfants, prévue pour être intégrée à SchoolSafe. Elle permettra aux parents de suivre les trajets entre la maison et l’école, de consulter la trajectoire parcourue et la localisation GPS de leur enfant.</p><p>Selon les capteurs et les capacités de la montre compatible, le système pourra également transmettre la température corporelle mesurée. Ces informations seront accessibles aux personnes autorisées, pour les enfants qui leur sont rattachés.</p><p><small>Fonctionnalité prévue, en attente d’intégration des équipements compatibles.</small></p></section>
<section><h2>Un espace adapté à chacun</h2><p>Administration, enseignants, parents et élèves disposent d’un accès adapté à leur profil. Les parents consultent les informations de leurs enfants rattachés. Les autorisations sont gérées par la direction.</p></section>
<section><h2>Accompagnement</h2><p>Pour une question sur votre dossier, une autorisation ou une information scolaire, contactez l’administration de votre établissement.</p></section>
<section aria-labelledby="official-site-title"><h2 id="official-site-title">Site officiel SchoolSafe</h2><p><a href="https://shoolsafe.tech/" target="_blank" rel="noopener noreferrer">shoolsafe.tech</a></p></section>
<p><small>SchoolSafe · Gestion et accompagnement scolaire</small></p>
<p><a href="schoolsafe-licenses.php">Licences et mentions l&eacute;gales</a></p>
</main>
</body>
</html>

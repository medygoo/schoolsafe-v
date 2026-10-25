<?php
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    header('Allow: GET, POST'); http_response_code(405); exit('Methode non autorisee.');
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
chdir(__DIR__);
$_REQUEST = ['modname' => 'misc/Portal.php', 'modfunc' => ''];
$level = ob_get_level();
ob_start();
require_once __DIR__ . '/Warehouse.php';
$allowed = User('STAFF_ID') && (AllowUse('Grades/StudentGrades.php')
    || (User('PROFILE') === 'teacher' && AllowUse('Grades/Grades.php')));
while (ob_get_level() > $level) ob_end_clean();
if (!$allowed) {
    http_response_code(403);
    exit('Permission de consultation des notes requise.');
}
require_once __DIR__ . '/modules/SchoolSafe/Gateway.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    && SchoolSafeEndpointGateway('rattrapage.php', $_SERVER['REQUEST_METHOD'], '', $_GET) === 'deny') {
    http_response_code(403); exit('Acces SchoolSafe non autorise.');
}
ob_start();
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rattrapage | SchoolSafe</title>
<link rel="stylesheet" href="schoolsafe-remediation/remediation.css">
</head>
<body>
<header><strong>School<span>Safe</span></strong><a href="Modules.php?modname=misc/Portal.php">Tableau de bord</a></header>
<main>
<nav aria-label="Fil d'Ariane">P&eacute;dagogie / Rattrapage</nav>
<h1>Rattrapage</h1>
<section aria-labelledby="rule-title">
<h2 id="rule-title">Comment fonctionne le rattrapage ?</h2>
<p>Le suivi est individuel et distinct pour chaque cours : math&eacute;matiques, fran&ccedil;ais, anglais ou toute autre mati&egrave;re.</p>
<ul>
<li>Le d&eacute;clenchement exige <strong>trois &eacute;valuations distinctes, chacune inf&eacute;rieure &agrave; 50 %</strong>, dans le m&ecirc;me cours.</li>
<li>Ces trois &eacute;valuations doivent comprendre <strong>au moins deux types</strong> parmi devoir, interrogation et examen.</li>
<li>Les notes sont accumul&eacute;es par cours, sans remise &agrave; z&eacute;ro au changement de trimestre ou de semestre. Il ne s'agit pas d'une moyenne inf&eacute;rieure &agrave; 50 %.</li>
<li>La d&eacute;tection est automatique : aucun dossier ne peut &ecirc;tre cr&eacute;&eacute; librement par un enseignant ou un administrateur.</li>
</ul>
<p class="example"><strong>Exemple :</strong> en math&eacute;matiques, un devoir &agrave; 40 %, une interrogation &agrave; 35 % et un autre devoir &agrave; 45 % remplissent les conditions. Trois devoirs seuls ne suffisent pas. Une note exactement &agrave; 50 % n'est pas un &eacute;chec pour cette r&egrave;gle.</p>
</section>
<section aria-labelledby="path-title">
<h2 id="path-title">Le parcours de l'&eacute;l&egrave;ve</h2>
<ol>
<li><strong>D&eacute;tection et information.</strong> Le dossier concerne le cours en difficult&eacute;. Le parent principal, la Direction p&eacute;dagogique et l'enseignant concern&eacute; sont inform&eacute;s.</li>
<li><strong>D&eacute;cision p&eacute;dagogique.</strong> La Direction p&eacute;dagogique valide ou refuse le dossier avant le d&eacute;but des s&eacute;ances.</li>
<li><strong>Accompagnement.</strong> L'enseignant titulaire suit les objectifs, dates, horaires, dur&eacute;es, pr&eacute;sences, reports, annulations et progr&egrave;s de chaque s&eacute;ance.</li>
<li><strong>R&eacute;&eacute;valuation.</strong> Le suivi peut aboutir &agrave; &laquo; R&eacute;ussi &raquo;, &laquo; &Agrave; reprendre &raquo; ou &laquo; Cl&ocirc;tur&eacute; &raquo;. Aucun seuil distinct de r&eacute;ussite n'est encore fix&eacute;.</li>
</ol>
<p>Les personnes concern&eacute;es doivent &eacute;galement recevoir les notifications de d&eacute;cision, programmation, modification, annulation, paiement, tenue des s&eacute;ances et r&eacute;sultats.</p>
</section>
<section aria-labelledby="finance-title">
<h2 id="finance-title">Paiement et r&eacute;partition</h2>
<p>La Direction p&eacute;dagogique choisit un accompagnement gratuit ou payant. Les paiements sont g&eacute;r&eacute;s dans <strong>Finance</strong>. Apr&egrave;s confirmation d'un paiement, la r&eacute;partition pr&eacute;vue est de <strong>40 % pour l'&eacute;tablissement et 60 % pour l'enseignant</strong>. Aucun tarif fixe n'est impos&eacute;.</p>
</section>
<section class="state" aria-labelledby="state-title">
<h2 id="state-title">&Eacute;tat du service</h2>
<p>D&eacute;tection, dossiers, suivi des s&eacute;ances et r&eacute;&eacute;valuation raccord&eacute;s aux notes SchoolSafe. Les paiements sont g&eacute;r&eacute;s dans Finance. Les notifications du parent principal sont consultables dans son &eacute;cran Notifications, apr&egrave;s v&eacute;rification de la liaison familiale existante. Une liaison absente ou ambigu&euml; bloque l'acc&egrave;s. Un dossier termin&eacute; n'est pas recr&eacute;&eacute; automatiquement avec les anciennes notes.</p>
</section>
<?php require __DIR__ . '/schoolsafe-remediation/Workspace.inc.php'; ?>
</main>
</body>
</html>

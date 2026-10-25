<?php
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
chdir(__DIR__);
$_REQUEST = ['modname' => 'Student_ID_Card/StudentIDCard.php', 'modfunc' => ''];
ob_start();
require_once __DIR__ . '/Warehouse.php';
ob_end_clean();
if (!User('STAFF_ID') || !AllowUse('Student_ID_Card/StudentIDCard.php')) {
    http_response_code(403);
    exit('Connexion Rosario et permission Cartes requises.');
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SchoolSafe - Studio retrouve</title>
<style>body{margin:0;font:14px Segoe UI,sans-serif;background:#fff;color:#17203a}header{padding:12px 16px;border-bottom:2px solid #1644a4}header p{margin:6px 0}iframe{display:block;width:100%;height:calc(100dvh - 125px);min-height:500px;border:0}a{color:#1644a4}</style></head><body>
<header><strong>Studio complet - livraison du 8 octobre 2026</strong><p>Atelier visuel : dossiers fictifs, QR de demonstration, reglages locaux. Aucune emission officielle ni enregistrement Rosario.</p><a href="/Modules.php?modname=misc/Portal.php">Tableau de bord</a></header>
<iframe title="Studio complet SchoolSafe retrouve" src="/schoolsafe-card-bridge/studio-validated-20261008/index.html"></iframe>
</body></html>

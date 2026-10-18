<?php
// SchoolSafe legal notice added 2026-10-14. Original credits and LICENSE are preserved.
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'self'");
$license = file_get_contents(__DIR__ . '/LICENSE');
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Licences | SchoolSafe</title>
<style>body{margin:0;background:#f4f7fa;color:#172d40;font:16px/1.6 system-ui,sans-serif}main{max-width:900px;margin:auto;padding:32px 24px}a{color:#126cac}h1{font-size:28px}pre{white-space:pre-wrap;overflow-wrap:anywhere;font:14px/1.7 monospace;border-top:1px solid #dbe4ec;padding-top:24px}details{padding:16px 0}summary{cursor:pointer}</style>
</head><body><main>
<a href="index.php">SchoolSafe</a>
<h1>Licences et mentions l&eacute;gales</h1>
<p>SchoolSafe est une version modifi&eacute;e du logiciel scolaire d'origine, distribu&eacute; sous la licence GNU GPL version 2. Ce logiciel est fourni sans garantie. La licence autorise sa redistribution et sa modification selon les conditions reproduites ci-dessous.</p>
<p>Copyright &copy; 2004-2009 The Miller Group &amp; Learners Circle.<br>
Copyright &copy; 2012-2026 RosarioSIS.</p>
<p>Les avis d'origine et la licence sont conserv&eacute;s dans les sources. Les adaptations de pr&eacute;sentation SchoolSafe du 14 octobre 2026 sont signal&eacute;es dans les fichiers modifi&eacute;s.</p>
<details><summary>Consulter la licence GNU GPL v2</summary><pre><?php echo htmlspecialchars($license, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre></details>
</main></body></html>

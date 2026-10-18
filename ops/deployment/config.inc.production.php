<?php
/**
 * SchoolSafe — configuration production (VPS), pilotée par variables d'environnement.
 *
 * Ce fichier est monté en lecture seule par ops/deployment/docker-compose.yml
 * sur /var/www/html/config.inc.php. Il ne contient AUCUN secret : le mot de passe
 * base vient de l'environnement (POSTGRES_PASSWORD, partagé avec le service db).
 * Modèle : config.inc.php de qualification locale, mêmes clés.
 */

$DatabaseType = 'postgresql';
$DatabaseServer = getenv('SCHOOLSAFE_DB_HOST') ?: 'db';
$DatabasePort = getenv('SCHOOLSAFE_DB_PORT') ?: '5432';
$DatabaseUsername = getenv('POSTGRES_USER') ?: 'schoolsafe';
$DatabasePassword = getenv('POSTGRES_PASSWORD');
$DatabaseName = getenv('POSTGRES_DB') ?: 'schoolsafe';

$DefaultSyear = getenv('SCHOOLSAFE_DEFAULT_SYEAR') ?: '2026';

// Pipeline PDF SchoolSafe (chromium headless). L'export PDF historique
// RosarioSIS (wkhtmltopdf) reste non qualifié : voir VPS-DEPLOY.md, limites connues.
$wkhtmltopdfPath = getenv('SCHOOLSAFE_PDF_BINARY') ?: '/usr/bin/chromium';
$DatabaseDumpPath = getenv('SCHOOLSAFE_PG_DUMP_PATH') ?: '/usr/bin/pg_dump';

$RosarioNotifyAddress = getenv('SCHOOLSAFE_NOTIFY_EMAIL') ?: '';
$RosarioErrorsAddress = getenv('SCHOOLSAFE_ERRORS_EMAIL') ?: '';

$RosarioLocales = ['en_US.utf8', 'fr_FR.utf8'];
$Timezone = getenv('SCHOOLSAFE_TIMEZONE') ?: 'Africa/Kinshasa';

define('ROSARIO_DISABLE_ADDON_UPLOAD', true);
define('ROSARIO_DISABLE_USAGE_STATISTICS', true);

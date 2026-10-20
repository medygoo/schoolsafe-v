<?php
/**
 * SchoolSafe L3 — route officielle du gestionnaire général PRODELI.
 *
 *   /plateforme/prodeli  ->  console générale existante (admin-console.php).
 *
 * Même application SchoolSafe, AUCUN second système d'identité : mêmes sessions
 * Rosario, mêmes tables staff/schools, mêmes gardes (SchoolSafeGlobalAdmin,
 * SchoolSafeSchoolAllowed, SchoolSafeScopeParse). Séparation stricte :
 *
 * PRODELI (global) : suivi et gestion globale autorisée des écoles, états
 *   d'activation, gestion administrative des comptes globaux, opérations
 *   centrales explicitement permises. N'accède JAMAIS automatiquement aux
 *   dossiers élèves, notes, conversations ni données pédagogiques (cette console
 *   ne requête que staff/schools/schoolsafe_account_state).
 *
 * ADMINISTRATEUR SCOLAIRE : gestion des comptes de son école, paramétrage
 *   scolaire autorisé, affectations/profils/délégations — jamais de GlobalAdmin
 *   (SCHOOLS NULL refusé hors global), jamais hors périmètre.
 *
 * Sémantique staff.SCHOOLS : NULL ou vide = toutes les écoles = privilège
 * global réservé ; un compte ordinaire ne l'obtient jamais implicitement
 * (User.php + SchoolSafeProfileDelegable + SchoolSafeSchoolScopedProfileRequiresSchool).
 */

chdir( __DIR__ );

require_once __DIR__ . '/admin-console.php';

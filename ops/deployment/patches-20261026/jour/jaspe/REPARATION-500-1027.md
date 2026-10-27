# INCIDENT 500 + RÉPARATION (27/10/2026 matin)

## L'incident
Le site entier en HTTP 500 (chaque page) — détecté par le contrôle de routine
du Device Hub (curl de supervision).

## La cause
L'activation Entry_Exit de la veille (26/10 soir) avait inséré la valeur
sérialisée MODULES avec des retours chariot parasites (fichier SQL écrit
sous Windows) → unserialize() échouait (Error at offset 325 of 355) dans
Warehouse.php ligne 600 → exception fatale sur chaque page.

## La réparation (27/10 ~01h30)
Réécriture propre de la valeur sérialisée (16 modules, validée octet par
octet en hex) — appliquée via psql. Le unserialize = array(16) ✓.
Les accès: accueil HTTP 200 ✓, Portal HTTP 200 ✓.

## Les fichiers de l'état final (dans ce dossier)
- assistant.js — le front JASPE propre (chips + écriture + couleurs, garde anti-doublon)
- Assistant.php — l'original restauré (le design bleu d'origine intact;
  les touches suivantes sont ré-appliquables depuis les sauvegardes locales)
- mobile-navigation.css — l'original nettoyé + le masque du bouton fermer
- worker-prod.php — le collecteur supervisé avec le garde DB adaptatif
- 045-device-hub-queue-collectors.sql — la migration Device Hub (dans le dossier parent)

## Leçon méthode
Chaque install de fichier: vérification MD5 aller + jamais de pipe
multi-fichiers (le STDIN unique consommé par le premier cat).

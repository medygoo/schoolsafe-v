# Voix de JASPE — service `schoolsafe-voice` (installé le 03/11/2026)

Synthèse vocale française locale pour JASPE, installée avec l'accord du propriétaire (« Oui, installe-la »).

- Moteur : [Piper](https://github.com/rhasspy/piper) 2023.11.14-2 (hors ligne, processeur seulement).
- Voix : `fr_FR-siwis-medium` (féminine, française). Jeu de données SIWIS, licence **CC-BY 4.0**
  (University of Edinburgh, https://datashare.is.ed.ac.uk/handle/10283/2353) — attribution requise.
- Empreintes SHA-256 des fichiers téléchargés : `sha256.txt`.
- Conteneur `schoolsafe-voice`, image `schoolsafe-voice:20261103`, réseau interne `schoolsafe-rosario-net`
  uniquement (aucun port publié), lecture seule, utilisateur 33, 1 Go / 2 processeurs, aucun journal de texte.
- Jeton : `/opt/schoolsafe-rosario/state/schoolsafe-jaspe/voice-token` (64 hexadécimaux, propriétaire 33, 600),
  monté en lecture seule dans le service et lu par `jaspe-voice.php` via le volume JASPE.
- Contrat : `GET /health` → `{"ready": true}` ; `POST /synthesize` `{"text"}` + `Authorization: Bearer` → WAV.

Exploitation, sur le VPS dans `/opt/schoolsafe-voice` : `./install.sh verify` ; retrait : `./install.sh remove`
(image et jeton conservés ; JASPE repasse alors à la voix du navigateur).

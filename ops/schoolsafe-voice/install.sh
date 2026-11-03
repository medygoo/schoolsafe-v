#!/usr/bin/env bash
# Installation de la voix de JASPE sur le VPS (03/11, accord du proprietaire « Oui, installe-la »).
# Usage, depuis /opt/schoolsafe-voice : ./install.sh prepare | run | verify | remove
set -euo pipefail
D=/opt/schoolsafe-voice
IMG=schoolsafe-voice:20261103
C=schoolsafe-voice
NET=schoolsafe-rosario-net
TOKEN=/opt/schoolsafe-rosario/state/schoolsafe-jaspe/voice-token
PIPER_URL=https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_linux_x86_64.tar.gz
VOICE_URL=https://huggingface.co/rhasspy/piper-voices/resolve/v1.0.0/fr/fr_FR/siwis/medium
log(){ printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }

prepare(){
  mkdir -p "$D/dl" "$D/build/voices"
  curl -fsSL -o "$D/dl/piper.tar.gz" "$PIPER_URL"
  for f in fr_FR-siwis-medium.onnx fr_FR-siwis-medium.onnx.json MODEL_CARD; do curl -fsSL -o "$D/dl/$f" "$VOICE_URL/$f"; done
  ( cd "$D/dl" && sha256sum piper.tar.gz fr_FR-siwis-medium.onnx fr_FR-siwis-medium.onnx.json MODEL_CARD ) > "$D/sha256.txt"
  tar -xzf "$D/dl/piper.tar.gz" -C "$D/build"
  cp "$D/dl/fr_FR-siwis-medium.onnx" "$D/dl/fr_FR-siwis-medium.onnx.json" "$D/dl/MODEL_CARD" "$D/build/voices/"
  docker build -q -t $IMG "$D" > "$D/image-id.txt"
  log "image $(cat "$D/image-id.txt")"; cat "$D/sha256.txt"
}

run(){
  if [ ! -s "$TOKEN" ]; then umask 077; openssl rand -hex 32 > "$TOKEN"; chown 33:33 "$TOKEN"; chmod 600 "$TOKEN"; log "jeton cree"; fi
  docker ps -a --format '{{.Names}}' | grep -qx $C && { echo "conteneur $C deja present"; exit 1; }
  docker run -d --name $C --network $NET --restart unless-stopped \
    --read-only --tmpfs /tmp:size=64m --user 33:33 --memory 1g --cpus 2 --pids-limit 64 \
    --security-opt no-new-privileges --cap-drop ALL \
    -v "$TOKEN":/run/voice-token:ro \
    --log-driver json-file --log-opt max-size=5m --log-opt max-file=2 \
    $IMG > "$D/container-id.txt"
  log "demarre"
}

verify(){
  s=unknown; for i in $(seq 1 12); do s=$(docker inspect -f '{{.State.Health.Status}}' $C); [ "$s" = healthy ] && break; sleep 5; done
  echo "sante=$s ports_publies=$(docker port $C | wc -l)"
  docker exec schoolsafe-rosario sh -c 'curl -s -m 5 http://schoolsafe-voice:18893/health; echo'
  docker exec schoolsafe-rosario sh -c 'curl -s -o /dev/null -w "sans_jeton=%{http_code}\n" -m 5 -X POST -H "Content-Type: application/json" -d "{\"text\":\"Bonjour\"}" http://schoolsafe-voice:18893/synthesize'
}

remove(){ docker rm -f $C >/dev/null 2>&1 || true; log "conteneur retire (image et jeton conserves)"; }

case "${1:-}" in prepare) prepare;; run) run;; verify) verify;; remove) remove;; *) echo "usage: $0 prepare|run|verify|remove"; exit 2;; esac

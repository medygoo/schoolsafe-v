#!/usr/bin/env bash
# JASPE : apparition sans flash, poses WebP, reponses rapides, ecoute Whisper, voix et gestes coherents
# dossier photos persistant, conteneur recréé avec la même configuration.
# Usage : release.sh prepare | deploy | verify | rollback   (fichiers dans ./files/)
set -euo pipefail

D=/opt/schoolsafe-releases/jaspe-rapide-20261104
C=schoolsafe-rosario
OLD=schoolsafe-rosario-pre-jaspe-rapide-20261104
BASE_TAG=schoolsafe-rosario:bulletin-identite-20261104
TAG=schoolsafe-rosario:jaspe-rapide-20261104
WWW=/var/www/html
MEDIA=/opt/schoolsafe-media
STATE=/opt/schoolsafe-rosario/state
FILES="jaspe-chat.php jaspe-listen.php modules/SchoolSafe_Jaspe/Assistant.php modules/SchoolSafe_Jaspe/assistant.js modules/SchoolSafe_Jaspe/voice.js modules/SchoolSafe_Jaspe/v12-rosario.js modules/SchoolSafe_Jaspe/jaspe2d/modules/jaspe2d/live-companion.js modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/manifest.json modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/manifest-png.json modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/chin2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/chin3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/equilibre-un-pied.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/eyelids-closed.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/grand-sourire.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-applaudir-contact.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-applaudir-ouvert.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-attente-main-jupe.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-bow.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-bowPrep.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-confused1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-confused2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-confused3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-crossArms.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-crossBack.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-crossPrep.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-cupsHalf.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-embarrassed1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-embarrassed2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-embarrassed3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-grateful1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-grateful2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-grateful3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-introOpen.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-introSelf.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-kneelStart.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-laughing1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-laughing2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-laughing3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-mains-reunies.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-noIndex.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-noIndexLeft.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-noIndexRight.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-non-main.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-noseScratch.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-noseTouch.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-pointerRight.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-preparer-genou.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-proud1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-proud2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-proud3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-reflechir.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-relieved1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-relieved2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-relieved3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-sad1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-sad2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-sad3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-shy1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-shy2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-shy3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-surpriseBody.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-surprisePrep.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-tired1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-tired2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-tired3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-un-genou-sol.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-yesDown.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/images-yesUp.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/intermediaire-bas.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/intermediaire-exterieur.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/jumpAir.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/jumpLift.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/jumpPrep.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/jupe-deux-mains.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/mains-hanches.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/originals-attentiveBody.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/originals-chin1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/originals-guide.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/originals-rest.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/originals-thumb2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/originals-wave.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/originals-worried1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/pointer.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/preparer-equilibre.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/preparer-hanches.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/preparer-salut.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/presenter.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/references-expliquer.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/smileMid.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/thumb1.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/thumb3.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/walkHalfL.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/walkHalfR.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/walkL.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/walkR.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/worried2.webp modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/webp/worried3.webp"

log(){ printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }

prepare(){
  [ "$(docker inspect -f '{{.Config.Image}}' $C)" = "$BASE_TAG" ] || { echo "image en service inattendue"; exit 1; }
  rm -rf "$D/build" && mkdir -p "$D/build"
  # Fins de ligne normalisees pour les fichiers texte seulement (jamais les images ni les binaires).
  for f in $FILES; do case "$f" in *.php|*.js|*.css|*.sql|*.html|*.json|*.txt|*.md|*.sh) sed -i 's/\r$//' "$D/files/$f";; esac; done
  tar -cf "$D/build/overlay.tar" -C "$D/files" --owner=0 --group=0 --mode=644 $FILES
  printf 'FROM %s\nADD overlay.tar %s/\nLABEL schoolsafe.patch.scope="jaspe-rapide-20261104"\n' "$BASE_TAG" "$WWW" > "$D/build/Dockerfile"
  docker build -q -t $TAG "$D/build" > "$D/image-id.txt"
  for f in $FILES; do
    a=$(sha256sum "$D/files/$f" | cut -c1-64)
    b=$(docker run --rm --network none --entrypoint sha256sum $TAG "$WWW/$f" | cut -c1-64)
    [ "$a" = "$b" ] || { echo "ECART $f"; exit 1; }
    case "$f" in *.php) docker run --rm --network none --entrypoint php $TAG -l "$WWW/$f" >/dev/null || { echo "SYNTAXE $f"; exit 1; };; esac
  done
  # Le reste de l'image est strictement l'image en service.
  docker run --rm --network none --entrypoint sh $BASE_TAG -c "cd $WWW && find . -type f -print0 | xargs -0 sha256sum" | sort > "$D/tree-base.txt"
  docker run --rm --network none --entrypoint sh $TAG -c "cd $WWW && find . -type f -print0 | xargs -0 sha256sum" | sort > "$D/tree-new.txt"
  added=$(comm -13 "$D/tree-base.txt" "$D/tree-new.txt" | wc -l); removed=$(comm -23 "$D/tree-base.txt" "$D/tree-new.txt" | wc -l)
  [ "$added" = 97 ] && [ "$removed" = 7 ] || { echo "ecart inattendu : +$added -$removed"; exit 1; }
  log "image $(cat "$D/image-id.txt") : carte officielle, rien d autre ne change"
}

deploy(){
  [ -s "$D/image-id.txt" ] || { echo "prepare non fait"; exit 1; }
  mkdir -p "$MEDIA/file-uploads/PickupPhotos" && chown 33:33 "$MEDIA/file-uploads/PickupPhotos" && chmod 750 "$MEDIA/file-uploads/PickupPhotos"
  docker ps --format '{{.Names}} {{.ID}}' | grep -v "^$C " | sort > "$D/others-before.txt"
  docker inspect $C > "$D/container-before.private.json"
  docker inspect $C | python3 -c "
import json,sys,subprocess
c=json.load(sys.stdin)[0]
ie=set(json.loads(subprocess.check_output(['docker','image','inspect',c['Image']]))[0]['Config']['Env'])
print(''.join(e+'\n' for e in c['Config']['Env'] if e not in ie),end='')" > "$D/rosario.private.env"
  chmod 600 "$D/rosario.private.env"
  log "arret"
  docker stop -t 20 $C >/dev/null
  docker rename $C $OLD
  docker update --restart=no $OLD >/dev/null
  docker cp "$OLD:/tmp/." - > "$D/tmp.private.tar"
  docker create --name $C \
    --network schoolsafe-rosario-net --ip 172.16.1.4 \
    -p 127.0.0.1:18081:80 \
    --env-file "$D/rosario.private.env" \
    -v /opt/schoolsafe-rosario/config.inc.php:$WWW/config.inc.php:ro \
    -v $MEDIA/school-logos:$WWW/assets/school-logos:rw \
    -v $MEDIA/user-photos:$WWW/assets/UserPhotos:rw \
    -v $MEDIA/student-photos:$WWW/assets/StudentPhotos:rw \
    -v $MEDIA/file-uploads:$WWW/FileUploads:rw \
    -v $STATE/schoolsafe-jaspe:/var/lib/schoolsafe-jaspe:rw \
    -v $STATE/schoolsafe-device-hub:/var/lib/schoolsafe-device-hub:rw \
    -v $STATE/schoolsafe-api:/var/lib/schoolsafe-api:rw \
    --restart unless-stopped \
    --log-driver json-file --log-opt max-size=10m --log-opt max-file=3 \
    --health-cmd 'curl -fsS -o /dev/null http://127.0.0.1/index.php || exit 1' \
    --health-interval 30s --health-timeout 5s --health-start-period 20s --health-retries 3 \
    $TAG > "$D/new-container-id.txt"
  mkdir -p "$D/sess"
  tar -xf "$D/tmp.private.tar" -C "$D/sess" --wildcards 'sess_*' './sess_*' --same-owner -p 2>/dev/null || true
  find "$D/sess" -mindepth 1 -maxdepth 1 ! -name 'sess_*' -exec rm -rf {} +
  tar -cf - -C "$D/sess" . | docker cp - "$C:/tmp/"
  rm -rf "$D/sess" "$D/tmp.private.tar"
  docker start $C >/dev/null
  log "demarre"
}

verify(){
  s=unknown
  for i in $(seq 1 24); do s=$(docker inspect -f '{{.State.Health.Status}}' $C); [ "$s" = healthy ] && break; sleep 5; done
  echo "sante=$s image=$(docker inspect -f '{{.Config.Image}}' $C)"
  echo "local=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18081/index.php) public=$(curl -s -o /dev/null -w '%{http_code}' https://app.shoolsafe.tech/)"
  echo "js=$(curl -s -o /dev/null -w '%{http_code}' https://app.shoolsafe.tech/schoolsafe-card-bridge/family-tab.js) css=$(curl -s -o /dev/null -w '%{http_code}' https://app.shoolsafe.tech/schoolsafe-card-bridge/family-tab.css) recherche_sans_session=$(curl -s -o /dev/null -w "%{http_code}" "https://app.shoolsafe.tech/schoolsafe-search.php?type=student&q=te")"
  docker exec $C sh -c 'echo sessions=$(ls /tmp | grep -c ^sess_) photos=$(find /var/www/html/assets/StudentPhotos -type f | wc -l) pickup_dir=$(stat -c %U /var/www/html/FileUploads/PickupPhotos)'
  docker ps --format '{{.Names}} {{.ID}}' | grep -v "^$C " | sort > /tmp/o1
  diff -q /tmp/o1 "$D/others-before.txt" >/dev/null && echo "autres_services=inchanges" || echo "autres_services=CHANGES"
  echo "erreurs_php_2min=$(docker logs --since 2m $C 2>&1 | grep -cE 'PHP (Fatal|Parse)|Uncaught' || true)"
}

rollback(){
  docker rm -f $C >/dev/null 2>&1 || true
  docker rename $OLD $C
  docker update --restart=unless-stopped $C >/dev/null
  docker start $C >/dev/null
  log "ancien conteneur remis en service"
}

case "${1:-}" in prepare) prepare;; deploy) deploy;; verify) verify;; rollback) rollback;; *) echo "usage: $0 prepare|deploy|verify|rollback"; exit 2;; esac

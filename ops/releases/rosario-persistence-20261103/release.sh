#!/usr/bin/env bash
# Rosario : figer l'etat reel du conteneur dans une image et sortir les donnees
# vivantes (photos eleves, FileUploads, cles /var/lib/schoolsafe-*, sessions)
# de la couche du conteneur vers des volumes persistants.
# Usage : release.sh prepare | deploy | verify | rollback
set -euo pipefail

D=/opt/schoolsafe-releases/rosario-persistence-20261103
C=schoolsafe-rosario
OLD=schoolsafe-rosario-pre-persistence-20261103
BASE_IMAGE=sha256:7ceefc00ceaf903b7d71f1fcf3d5104f4891dda845e2184b034f460c53b24694
TAG=schoolsafe-rosario:persistence-20261103
COMMIT=34fcd41ab91989a671975f478d0d2c7a35903b66
WWW=/var/www/html
MEDIA=/opt/schoolsafe-media
STATE=/opt/schoolsafe-rosario/state
EXCLUDE_RE='(\.orig-|\.pre-debug-|/modules-value\.txt |^\./config\.inc\.php |^\./assets/(UserPhotos|school-logos|StudentPhotos)/|^\./FileUploads/)'

log(){ printf '[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }

tree_hashes(){ # "$@" = prefixe docker exec/run
  "$@" sh -c "cd $WWW && find . -type f -print0 | xargs -0 sha256sum" | awk '{print $2" "$1}' | grep -vE "$EXCLUDE_RE" | sort
}

prepare(){
  mkdir -p "$D/build" && chmod 700 "$D"
  [ "$(docker inspect -f '{{.Image}}' $C)" = "$BASE_IMAGE" ] || { echo "image inattendue"; exit 1; }
  docker inspect $C > "$D/container-before.private.json"
  docker ps -a --format '{{.Names}} {{.ID}} {{.Status}}' | sort > "$D/containers-before.txt"
  log "sauvegarde base"
  docker exec schoolsafe-rosario-db pg_dump -U postgres -Fc rosario_addons_test > "$D/database-before.private.dump"
  log "liste des fichiers derives"
  docker diff $C | awk '$1=="A"||$1=="C"{print $2}' | grep "^$WWW/" | sed "s#^$WWW/##" \
    | grep -vE '\.orig-|\.pre-debug-|modules-value\.txt$|^(assets/(UserPhotos|school-logos|StudentPhotos)|FileUploads)(/|$)|^config\.inc\.php$' \
    | while read -r f; do docker exec $C test -f "$WWW/$f" && echo "$f" || true; done > "$D/overlay-files.txt"
  log "$(wc -l < "$D/overlay-files.txt") fichiers dans la surcouche"
  docker exec -i $C tar -cf - -C $WWW -T - < "$D/overlay-files.txt" > "$D/build/overlay.tar"
  docker tag $BASE_IMAGE schoolsafe-rosario:base-7ceefc00
  cat > "$D/build/Dockerfile" <<EOF
FROM schoolsafe-rosario:base-7ceefc00
ADD overlay.tar $WWW/
LABEL schoolsafe.patch.commit="$COMMIT" schoolsafe.patch.scope="prod-drift-capture-persistence-20261103"
EOF
  log "construction image"
  docker build -q -t $TAG "$D/build" > "$D/image-id.txt"
  log "preuve image == conteneur"
  tree_hashes docker exec $C > "$D/tree-container.txt"
  tree_hashes docker run --rm --network none --entrypoint "" $TAG > "$D/tree-image.txt"
  if diff -q "$D/tree-container.txt" "$D/tree-image.txt" >/dev/null; then
    log "IDENTIQUE : $(wc -l < "$D/tree-image.txt") fichiers"
  else
    diff "$D/tree-container.txt" "$D/tree-image.txt" | head -20; echo "ECART image/conteneur"; exit 1
  fi
}

env_file(){
  docker inspect $OLD | python3 -c "
import json,sys,subprocess
c=json.load(sys.stdin)[0]
ie=set(json.loads(subprocess.check_output(['docker','image','inspect',c['Image']]))[0]['Config']['Env'])
print(''.join(e+'\n' for e in c['Config']['Env'] if e not in ie),end='')" > "$D/rosario.private.env"
  chmod 600 "$D/rosario.private.env"
}

copy_out(){ # $1 chemin conteneur, $2 dossier hote
  mkdir -p "$2"
  docker cp "$OLD:$1/." - | tar -xf - -C "$2" --same-owner -p
}

deploy(){
  [ -s "$D/image-id.txt" ] && diff -q "$D/tree-container.txt" "$D/tree-image.txt" >/dev/null || { echo "prepare non valide"; exit 1; }
  docker ps --format '{{.Names}} {{.ID}} {{.Status}}' | grep -v "^$C " | sort > "$D/others-before.txt"
  log "arret de l'ancien conteneur"
  docker stop -t 20 $C >/dev/null
  docker rename $C $OLD
  docker update --restart=no $OLD >/dev/null
  log "sessions et donnees vivantes"
  docker cp "$OLD:/tmp/." - > "$D/tmp.private.tar"
  copy_out $WWW/assets/StudentPhotos $MEDIA/student-photos
  copy_out $WWW/FileUploads          $MEDIA/file-uploads
  copy_out /var/lib/schoolsafe-jaspe      $STATE/schoolsafe-jaspe
  copy_out /var/lib/schoolsafe-device-hub $STATE/schoolsafe-device-hub
  copy_out /var/lib/schoolsafe-api        $STATE/schoolsafe-api
  chmod 700 "$STATE"
  env_file
  log "creation du nouveau conteneur"
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
  # sessions uniquement (pas les fichiers de debogage de /tmp)
  mkdir -p "$D/sess"
  tar -xf "$D/tmp.private.tar" -C "$D/sess" --wildcards 'sess_*' './sess_*' --same-owner -p 2>/dev/null || true
  find "$D/sess" -mindepth 1 -maxdepth 1 ! -name 'sess_*' -exec rm -rf {} +
  tar -cf - -C "$D/sess" . | docker cp - "$C:/tmp/"
  rm -rf "$D/sess"
  docker start $C >/dev/null
  log "demarre"
}

verify(){
  s=unknown
  for i in $(seq 1 24); do s=$(docker inspect -f '{{.State.Health.Status}}' $C); [ "$s" = healthy ] && break; sleep 5; done
  echo "sante=$s"
  echo "local=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18081/index.php) public=$(curl -s -o /dev/null -w '%{http_code}' https://app.shoolsafe.tech/)"
  docker exec $C sh -c 'echo sessions=$(ls /tmp | grep -c ^sess_) photos=$(find /var/www/html/assets/StudentPhotos -type f | wc -l) uploads=$(find /var/www/html/FileUploads -type f | wc -l) cles=$(find /var/lib/schoolsafe-jaspe /var/lib/schoolsafe-device-hub /var/lib/schoolsafe-api -type f | wc -l)'
  echo "photos_dans_couche=$(docker diff $C | grep -c 'assets/StudentPhotos/2026/' || true)"
  docker ps --format '{{.Names}} {{.ID}}' | grep -v "^$C " | sort > /tmp/o1; awk '{print $1" "$2}' "$D/others-before.txt" > /tmp/o2
  diff -q /tmp/o1 /tmp/o2 >/dev/null && echo "autres_services=inchanges" || echo "autres_services=CHANGES"
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

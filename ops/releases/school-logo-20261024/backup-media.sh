#!/bin/sh
# Root-only daily archive of the dedicated logo bind. Never archive all of assets/.
set -eu
umask 077
source_dir=/opt/schoolsafe-media/school-logos
backup_dir=/opt/schoolsafe-media-backups
[ "$(id -u)" -eq 0 ] && [ -d "$source_dir" ] && [ ! -L "$source_dir" ]
[ "$(stat -c '%u:%g:%a' "$source_dir")" = '0:33:2770' ]
mkdir -p -m 0700 "$backup_dir"
[ "$(stat -c '%u:%a' "$backup_dir")" = '0:700' ]
exec 9>"$backup_dir/.lock"
flock -n 9 || exit 0
stamp=$(date -u +%Y%m%dT%H%M%SZ)
tmp="$backup_dir/.school-logos-$stamp-$$.tmp"
trap 'rm -f -- "$tmp"' EXIT HUP INT TERM
tar -C /opt/schoolsafe-media --one-file-system --numeric-owner -cf "$tmp" school-logos
tar -tf "$tmp" >/dev/null
mv -- "$tmp" "$backup_dir/school-logos-$stamp.tar"
# Retain 14 daily archives; do not delete the most recent successful copy.
find "$backup_dir" -maxdepth 1 -type f -name 'school-logos-*.tar' -mtime +14 -delete

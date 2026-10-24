#!/bin/sh
# Preserve native staff photos without collecting other assets or exposing archives.
set -eu
umask 077
source_dir=/opt/schoolsafe-media/user-photos
backup_dir=/opt/schoolsafe-photo-backups
[ "$(id -u)" -eq 0 ] && [ -d "$source_dir" ] && [ ! -L "$source_dir" ]
[ "$(stat -c '%u:%g:%a' "$source_dir")" = '0:33:2770' ]
mkdir -p -m 0700 "$backup_dir"
[ "$(stat -c '%u:%a' "$backup_dir")" = '0:700' ]
exec 9>"$backup_dir/.lock"
flock -n 9 || exit 0
stamp=$(date -u +%Y%m%dT%H%M%SZ)
tmp="$backup_dir/.user-photos-$stamp-$$.tmp"
trap 'rm -f -- "$tmp"' EXIT HUP INT TERM
tar -C /opt/schoolsafe-media --one-file-system --numeric-owner -cf "$tmp" user-photos
tar -tf "$tmp" >/dev/null
mv -- "$tmp" "$backup_dir/user-photos-$stamp.tar"
find "$backup_dir" -maxdepth 1 -type f -name 'user-photos-*.tar' -mtime +14 -delete

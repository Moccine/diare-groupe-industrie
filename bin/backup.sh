#!/usr/bin/env bash

# Sauvegarde isolée de Diaré Groupe Industrie :
#   - base diare_groupe_industrie
#   - médias backend/public/uploads/media
#
# Le mot de passe n'est ni affiché, ni écrit dans ce script.
# Il est lu depuis backend/.env.local vers un fichier temporaire en 0600.
#
# Usage :
#   sudo ./bin/backup.sh
#
# Destination :
#   BACKUP_DIR=/var/backups/diare-groupe-industrie
# Conservation :
#   7 archives quotidiennes et 4 archives du dimanche.

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
BACKEND_DIR="${PROJECT_ROOT}/backend"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.2}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/diare-groupe-industrie}"
STAMP="$(date +%Y%m%d-%H%M%S)"
CNF=""

cleanup()
{
    if [ -n "${CNF}" ] && [ -f "${CNF}" ]; then
        rm -f "${CNF}"
    fi
}

trap cleanup EXIT

if [ "$(id -u)" -ne 0 ]; then
    echo "ERREUR : sudo ./bin/backup.sh"
    exit 1
fi

if command -v mariadb-dump >/dev/null 2>&1; then
    DUMP_BIN="$(command -v mariadb-dump)"
elif command -v mysqldump >/dev/null 2>&1; then
    DUMP_BIN="$(command -v mysqldump)"
else
    echo "ERREUR : mariadb-dump ou mysqldump est absent."
    exit 1
fi

install -d -o root -g root -m 0750 "${BACKUP_DIR}/daily" "${BACKUP_DIR}/weekly"

CNF="$(mktemp)"
chmod 600 "${CNF}"
"${PHP_BIN}" "${PROJECT_ROOT}/bin/lib/prod-env.php" write-cnf "${CNF}"
chmod 600 "${CNF}"

DB_ARCHIVE="${BACKUP_DIR}/daily/db-${STAMP}.sql.gz"
MEDIA_ARCHIVE="${BACKUP_DIR}/daily/media-${STAMP}.tar.gz"
TMP_DB="${DB_ARCHIVE}.partial"

echo "Sauvegarde de diare_groupe_industrie..."
"${DUMP_BIN}" \
    --defaults-extra-file="${CNF}" \
    --single-transaction \
    --quick \
    --no-tablespaces \
    diare_groupe_industrie | gzip -c > "${TMP_DB}"

if [ ! -s "${TMP_DB}" ]; then
    rm -f "${TMP_DB}"
    echo "ERREUR : la sauvegarde SQL est vide."
    exit 1
fi

mv "${TMP_DB}" "${DB_ARCHIVE}"
chmod 640 "${DB_ARCHIVE}"

echo "Sauvegarde des médias..."
tar -C "${BACKEND_DIR}/public/uploads" -czf "${MEDIA_ARCHIVE}" media
chmod 640 "${MEDIA_ARCHIVE}"

if [ "$(date +%u)" = "7" ]; then
    cp -a "${DB_ARCHIVE}" "${BACKUP_DIR}/weekly/db-${STAMP}.sql.gz"
    cp -a "${MEDIA_ARCHIVE}" "${BACKUP_DIR}/weekly/media-${STAMP}.tar.gz"
fi

prune()
{
    local dir="$1"
    local pattern="$2"
    local keep="$3"
    find "${dir}" -maxdepth 1 -type f -name "${pattern}" | sort -r | awk "NR>${keep}" | while read -r old_file; do
        rm -f "${old_file}"
    done
}

prune "${BACKUP_DIR}/daily" 'db-*.sql.gz' 7
prune "${BACKUP_DIR}/daily" 'media-*.tar.gz' 7
prune "${BACKUP_DIR}/weekly" 'db-*.sql.gz' 4
prune "${BACKUP_DIR}/weekly" 'media-*.tar.gz' 4

echo "Sauvegarde terminée : ${BACKUP_DIR}"
echo "SQL    : ${DB_ARCHIVE}"
echo "Médias : ${MEDIA_ARCHIVE}"

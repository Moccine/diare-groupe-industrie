#!/usr/bin/env bash

# Contrôles après déploiement. N'affiche aucun secret.
#
# Usage :
#   sudo ./bin/validate.sh
#   sudo SKIP_HTTP_CHECK=1 ./bin/validate.sh

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
BACKEND_DIR="${PROJECT_ROOT}/backend"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.2}"
WEB_USER="${WEB_USER:-www-data}"

if [ "$(basename "${PROJECT_ROOT}")" = "diare-groupe-industrie" ]; then
    MESSENGER_SERVICE="${MESSENGER_SERVICE_NAME:-diare-groupe-industrie-messenger}"
else
    MESSENGER_SERVICE="${MESSENGER_SERVICE_NAME:-diare-groupe-industrie-messenger-$(basename "${PROJECT_ROOT}" | tr -cd 'A-Za-z0-9-')}"
fi

if [ "$(id -u)" -ne 0 ]; then
    echo "ERREUR : sudo ./bin/validate.sh"
    exit 1
fi

failed=0

ok()
{
    echo "OK    $*"
}

bad()
{
    echo "ÉCHEC $*"
    failed=1
}

run_as_web_user()
{
    sudo -u "${WEB_USER}" -H env APP_ENV=prod APP_DEBUG=0 "$@"
}

echo "Validation Diaré Groupe Industrie"
echo "Projet : ${PROJECT_ROOT}"

if [ ! -f "${BACKEND_DIR}/.env.local" ]; then
    bad "backend/.env.local est absent"
else
    ok "backend/.env.local présent"
fi

if [ ! -s "${BACKEND_DIR}/public/build/entrypoints.json" ]; then
    bad "build frontend absent"
else
    ok "build frontend"
fi

if [ ! -d "${BACKEND_DIR}/public/uploads/media" ]; then
    bad "dossier médias absent"
else
    ok "dossier médias présent"
fi

if id "${WEB_USER}" >/dev/null 2>&1 && sudo -u "${WEB_USER}" test -w "${BACKEND_DIR}/public/uploads/media"; then
    ok "médias inscriptibles par ${WEB_USER}"
else
    bad "médias non inscriptibles par ${WEB_USER}"
fi

if sudo -u "${WEB_USER}" test -w "${BACKEND_DIR}/var/cache" && sudo -u "${WEB_USER}" test -w "${BACKEND_DIR}/var/log"; then
    ok "var/cache et var/log inscriptibles"
else
    bad "var/cache ou var/log non inscriptible"
fi

if [ -d "${BACKEND_DIR}/var/private/job-applications" ] && sudo -u "${WEB_USER}" test -w "${BACKEND_DIR}/var/private/job-applications"; then
    ok "dossier privé des CV inscriptible"
else
    bad "dossier privé des CV absent ou non inscriptible"
fi

if [ -f "${BACKEND_DIR}/vendor/autoload.php" ]; then
    if run_as_web_user "${PHP_BIN}" "${PROJECT_ROOT}/bin/lib/prod-env.php" check; then
        ok "variables critiques"
    else
        bad "variables critiques"
    fi

    cd "${BACKEND_DIR}"
    if run_as_web_user "${PHP_BIN}" bin/console lint:container --env=prod --no-debug >/dev/null; then
        ok "conteneur Symfony"
    else
        bad "conteneur Symfony"
    fi

    if run_as_web_user "${PHP_BIN}" bin/console debug:router --env=prod --no-debug >/dev/null; then
        ok "routes"
    else
        bad "routes"
    fi

    if run_as_web_user "${PHP_BIN}" bin/console doctrine:query:sql "SELECT 1" --env=prod --no-debug >/dev/null; then
        ok "connexion base"
    else
        bad "connexion base"
    fi

    if run_as_web_user "${PHP_BIN}" bin/console doctrine:migrations:up-to-date --env=prod --no-debug >/dev/null; then
        ok "migrations à jour"
    else
        bad "migrations en attente ou commande indisponible"
    fi

    if [ -d "${BACKEND_DIR}/var/cache/prod" ]; then
        ok "cache prod"
    else
        bad "cache prod absent"
    fi
else
    bad "vendor absent : lancer bin/deploy.sh"
fi

if systemctl is-active --quiet "${MESSENGER_SERVICE}" 2>/dev/null; then
    ok "worker ${MESSENGER_SERVICE}"
else
    bad "worker ${MESSENGER_SERVICE} inactif"
fi

if [ "${SKIP_HTTP_CHECK:-0}" != "1" ] && [ -f "${BACKEND_DIR}/vendor/autoload.php" ]; then
    if DEFAULT_URI="$(run_as_web_user "${PHP_BIN}" "${PROJECT_ROOT}/bin/lib/prod-env.php" print-uri)"; then
        for path in "/" "/robots.txt" "/sitemap.xml" "/administration/connexion"; do
            code="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 20 "${DEFAULT_URI}${path}")"
            if [ "${code}" = "200" ]; then
                ok "HTTP ${path}"
            else
                bad "HTTP ${path} a répondu ${code}"
            fi
        done
    else
        bad "DEFAULT_URI illisible"
    fi
fi

echo ""
if [ "${failed}" -ne 0 ]; then
    echo "Validation en échec."
    exit 1
fi

echo "Validation réussie. Aucun secret n'a été affiché."

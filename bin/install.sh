#!/usr/bin/env bash

# Préparation d'un nouveau déploiement Diaré Groupe Industrie.
# Ce script ne modifie aucun autre site du VPS :
# il ne fait ni apt, ni drop de base, ni changement de VirtualHost.
#
# Usage :
#   sudo ./bin/install.sh

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
BACKEND_DIR="${PROJECT_ROOT}/backend"

PHP_BIN="${PHP_BIN:-/usr/bin/php8.2}"
PHP_MIN_VERSION="${PHP_MIN_VERSION:-8.2.7}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

missing=0

warn()
{
    echo "AVERTISSEMENT : $*"
}

fail()
{
    echo "ERREUR : $*"
    missing=1
}

echo "============================================================"
echo "Préparation Diaré Groupe Industrie"
echo "============================================================"
echo "Projet : ${PROJECT_ROOT}"
echo "Ce script ne touche pas aux autres applications du VPS."
echo "============================================================"

if [ "$(id -u)" -ne 0 ]; then
    echo "ERREUR : exécuter avec sudo ./bin/install.sh"
    exit 1
fi

if [ ! -f "${BACKEND_DIR}/composer.json" ] || [ ! -f "${BACKEND_DIR}/composer.lock" ]; then
    echo "ERREUR : backend/composer.json ou composer.lock est absent."
    exit 1
fi

if [ ! -f "${BACKEND_DIR}/package.json" ] || [ ! -f "${BACKEND_DIR}/package-lock.json" ]; then
    echo "ERREUR : backend/package.json ou package-lock.json est absent."
    exit 1
fi

if [ ! -f "${BACKEND_DIR}/public/index.php" ]; then
    echo "ERREUR : backend/public/index.php est absent."
    exit 1
fi

if [ ! -x "${PHP_BIN}" ]; then
    fail "PHP introuvable (${PHP_BIN}). Paquet attendu : php8.2-cli, sans changer la version PHP des autres sites."
else
    echo "PHP : $("${PHP_BIN}" -r 'echo PHP_VERSION;')"
    if ! env PHP_BIN_MIN="${PHP_MIN_VERSION}" "${PHP_BIN}" -r '
        if (version_compare(PHP_VERSION, getenv("PHP_BIN_MIN") ?: "8.2.7", "<")) {
            fwrite(STDERR, "PHP ".PHP_VERSION." est trop ancien.\n");
            exit(1);
        }
    '; then
        fail "PHP ${PHP_MIN_VERSION} minimum requis."
    fi

    required_extensions="ctype iconv pdo pdo_mysql mbstring xml intl gd zip curl fileinfo tokenizer"
    for extension in ${required_extensions}; do
        if ! "${PHP_BIN}" -m | grep -qi "^${extension}$"; then
            fail "extension PHP absente : ${extension}"
        fi
    done

    if ! "${PHP_BIN}" -r 'exit(function_exists("imagewebp") ? 0 : 1);'; then
        fail "GD sans support WebP (paquet php8.2-gd)."
    fi

    if ! "${PHP_BIN}" -m | grep -qi '^Zend OPcache$'; then
        warn "OPcache est absent. Le site fonctionne, mais la production sera plus lente."
    fi
fi

for command_name in composer node npm git apache2ctl; do
    if ! command -v "${command_name}" >/dev/null 2>&1; then
        fail "commande absente : ${command_name}"
    fi
done

if ! command -v mariadb >/dev/null 2>&1 && ! command -v mysql >/dev/null 2>&1; then
    warn "client MariaDB absent. La sauvegarde bin/backup.sh en aura besoin (mariadb-client)."
fi

if ! command -v mariadb-dump >/dev/null 2>&1 && ! command -v mysqldump >/dev/null 2>&1; then
    warn "mariadb-dump / mysqldump est absent."
fi

if ! command -v certbot >/dev/null 2>&1; then
    warn "Certbot est absent. Il ne sert qu'au moment du HTTPS, après contrôle DNS."
fi

if ! id "${WEB_USER}" >/dev/null 2>&1; then
    fail "utilisateur système absent : ${WEB_USER}"
fi

if ! getent group "${WEB_GROUP}" >/dev/null 2>&1; then
    fail "groupe système absent : ${WEB_GROUP}"
fi

install -d -o "${WEB_USER}" -g "${WEB_GROUP}" -m 2775 \
    "${BACKEND_DIR}/var" \
    "${BACKEND_DIR}/var/cache" \
    "${BACKEND_DIR}/var/log" \
    "${BACKEND_DIR}/public/uploads" \
    "${BACKEND_DIR}/public/uploads/media" \
    /var/www/.composer \
    /var/www/.npm
install -d -o "${WEB_USER}" -g "${WEB_GROUP}" -m 2770 \
    "${BACKEND_DIR}/var/private" \
    "${BACKEND_DIR}/var/private/job-applications"

if [ ! -f "${BACKEND_DIR}/.env.local" ]; then
    cp "${BACKEND_DIR}/.env.prod.dist" "${BACKEND_DIR}/.env.local"
    chown "${WEB_USER}:${WEB_GROUP}" "${BACKEND_DIR}/.env.local"
    chmod 640 "${BACKEND_DIR}/.env.local"
    echo ""
    echo "backend/.env.local a été créé depuis .env.prod.dist."
    echo "Renseignez les placeholders avant le premier déploiement."
    missing=1
fi

chown "${WEB_USER}:${WEB_GROUP}" "${BACKEND_DIR}/.env.local"
chmod 640 "${BACKEND_DIR}/.env.local"

if grep -E '^[A-Za-z0-9_]+=' "${BACKEND_DIR}/.env.local" | grep -q 'CHANGE_ME\|change-me-with-a-long-random-secret\|example.com'; then
    fail "backend/.env.local contient encore des placeholders. Aucune valeur n'est affichée."
fi

echo ""
if [ "${missing}" -ne 0 ]; then
    echo "Préparation incomplète. Aucun paquet n'a été installé automatiquement."
    echo "Installez uniquement les paquets manquants, sans changer le PHP global des autres sites."
    exit 1
fi

echo "Préparation terminée."
echo "Prochaine étape : sudo ./bin/create-apache-vhost.sh --domain DOMAINE --server-admin EMAIL"
echo "Puis : sudo ./bin/deploy.sh"
echo "Le VirtualHost des autres sites n'a pas été modifié."

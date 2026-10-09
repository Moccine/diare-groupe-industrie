#!/usr/bin/env bash

# Déploiement natif de Diaré Groupe Industrie.
# Le code est mis à jour dans le dossier courant. Les uploads ne sont pas supprimés.
#
# Usage :
#   cd /var/www/diare-groupe-industrie
#   sudo ./bin/deploy.sh
#
# Variables facultatives :
#   DEPLOY_SKIP_GIT=1          ne pas faire git pull
#   BACKUP_BEFORE_MIGRATE=1    lance bin/backup.sh avant les migrations
#   SKIP_HTTP_CHECK=1          ignore le contrôle HTTP (DNS pas encore en place)
#   PHP_BIN=/usr/bin/php8.2
#   MESSENGER_SERVICE_NAME=diare-groupe-industrie-messenger

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
BACKEND_DIR="${PROJECT_ROOT}/backend"

WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.2}"
PHP_MIN_VERSION="${PHP_MIN_VERSION:-8.2.7}"
COMPOSER_BIN="$(command -v composer || true)"
NPM_BIN="$(command -v npm || true)"

if [ -n "${MESSENGER_SERVICE_NAME:-}" ]; then
    MESSENGER_SERVICE="${MESSENGER_SERVICE_NAME}"
elif [ "$(basename "${PROJECT_ROOT}")" = "diare-groupe-industrie" ]; then
    MESSENGER_SERVICE="diare-groupe-industrie-messenger"
else
    MESSENGER_SERVICE="diare-groupe-industrie-messenger-$(basename "${PROJECT_ROOT}" | tr -cd 'A-Za-z0-9-')"
fi

MESSENGER_SERVICE_FILE="/etc/systemd/system/${MESSENGER_SERVICE}.service"

deployment_error()
{
    local exit_code=$?
    trap - ERR

    if systemctl is-active --quiet "${MESSENGER_SERVICE}" 2>/dev/null; then
        systemctl stop "${MESSENGER_SERVICE}" || true
    fi

    echo ""
    echo "============================================================"
    echo "ERREUR : le déploiement Diaré a échoué (code ${exit_code})."
    echo "Le worker Messenger reste arrêté."
    echo "Les uploads n'ont pas été supprimés."
    echo "Un rollback de code ne défait pas une migration déjà exécutée."
    echo "============================================================"
    exit "${exit_code}"
}

trap deployment_error ERR

run_as_web_user()
{
    sudo -u "${WEB_USER}" -H env \
        APP_ENV=prod \
        APP_DEBUG=0 \
        COMPOSER_HOME=/var/www/.composer \
        npm_config_cache=/var/www/.npm \
        "$@"
}

ensure_command()
{
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "ERREUR : la commande '$1' est absente."
        exit 1
    fi
}

echo "============================================================"
echo "Déploiement Diaré Groupe Industrie"
echo "============================================================"
echo "Date     : $(date)"
echo "Projet   : ${PROJECT_ROOT}"
echo "Backend  : ${BACKEND_DIR}"
echo "PHP      : ${PHP_BIN}"
echo "Worker   : ${MESSENGER_SERVICE}"
echo "============================================================"

if [ "$(id -u)" -ne 0 ]; then
    echo "ERREUR : sudo ./bin/deploy.sh"
    exit 1
fi

for required in \
    "${PROJECT_ROOT}/.git" \
    "${BACKEND_DIR}/composer.json" \
    "${BACKEND_DIR}/composer.lock" \
    "${BACKEND_DIR}/package.json" \
    "${BACKEND_DIR}/package-lock.json" \
    "${BACKEND_DIR}/bin/console" \
    "${BACKEND_DIR}/public/index.php" \
    "${BACKEND_DIR}/.env.local"
do
    if [ ! -e "${required}" ]; then
        echo "ERREUR : élément absent : ${required}"
        exit 1
    fi
done

if [ ! -x "${PHP_BIN}" ]; then
    echo "ERREUR : PHP introuvable : ${PHP_BIN}"
    exit 1
fi

if ! env PHP_MIN_VERSION="${PHP_MIN_VERSION}" "${PHP_BIN}" -r '
    if (version_compare(PHP_VERSION, getenv("PHP_MIN_VERSION") ?: "8.2.7", "<")) {
        fwrite(STDERR, "PHP ".PHP_VERSION." est inférieur au minimum requis.\n");
        exit(1);
    }
'; then
    exit 1
fi

if [ -z "${COMPOSER_BIN}" ] || [ -z "${NPM_BIN}" ]; then
    echo "ERREUR : composer ou npm est absent."
    exit 1
fi

ensure_command git
ensure_command sudo
ensure_command systemctl
ensure_command apache2ctl
ensure_command install
ensure_command find

if systemctl is-active --quiet "${MESSENGER_SERVICE}" 2>/dev/null; then
    echo "Arrêt temporaire du worker Messenger..."
    systemctl stop "${MESSENGER_SERVICE}"
fi

if [ "${DEPLOY_SKIP_GIT:-0}" = "1" ]; then
    echo "Git pull ignoré (DEPLOY_SKIP_GIT=1)."
    CURRENT_BRANCH="$(git -C "${PROJECT_ROOT}" rev-parse --abbrev-ref HEAD)"
else
    if ! git config --global --get-all safe.directory 2>/dev/null | grep -Fxq "${PROJECT_ROOT}"; then
        git config --global --add safe.directory "${PROJECT_ROOT}"
    fi

    CURRENT_BRANCH="$(git -C "${PROJECT_ROOT}" rev-parse --abbrev-ref HEAD)"
    if [ -z "${CURRENT_BRANCH}" ] || [ "${CURRENT_BRANCH}" = "HEAD" ]; then
        echo "ERREUR : branche Git introuvable."
        exit 1
    fi

    echo "Branche : ${CURRENT_BRANCH}"
    git -C "${PROJECT_ROOT}" fetch origin "${CURRENT_BRANCH}"
    git -C "${PROJECT_ROOT}" pull --ff-only origin "${CURRENT_BRANCH}"
fi

echo "Normalisation des permissions..."
chown -R "${WEB_USER}:${WEB_GROUP}" "${PROJECT_ROOT}"
chmod -R g+rX "${PROJECT_ROOT}"
find "${PROJECT_ROOT}" -type d -exec chmod g+s {} +
chmod +x "${PROJECT_ROOT}/bin/"*.sh "${PROJECT_ROOT}/bin/lib/"*.php "${BACKEND_DIR}/bin/console"
chmod 640 "${BACKEND_DIR}/.env.local"
chown "${WEB_USER}:${WEB_GROUP}" "${BACKEND_DIR}/.env.local"

install -d -o "${WEB_USER}" -g "${WEB_GROUP}" -m 2775 \
    /var/www/.composer \
    /var/www/.npm \
    "${BACKEND_DIR}/var" \
    "${BACKEND_DIR}/var/cache" \
    "${BACKEND_DIR}/var/log" \
    "${BACKEND_DIR}/public/uploads" \
    "${BACKEND_DIR}/public/uploads/media"
install -d -o "${WEB_USER}" -g "${WEB_GROUP}" -m 2770 \
    "${BACKEND_DIR}/var/private" \
    "${BACKEND_DIR}/var/private/job-applications"

echo "Composer (production)..."
cd "${BACKEND_DIR}"
run_as_web_user "${PHP_BIN}" "${COMPOSER_BIN}" install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --classmap-authoritative

if [ ! -f "${BACKEND_DIR}/vendor/autoload_runtime.php" ]; then
    echo "ERREUR : vendor/autoload_runtime.php est absent."
    exit 1
fi

run_as_web_user "${PHP_BIN}" "${COMPOSER_BIN}" check-platform-reqs --no-dev

echo "Contrôle de l'environnement de production (sans afficher les secrets)..."
run_as_web_user "${PHP_BIN}" "${PROJECT_ROOT}/bin/lib/prod-env.php" check

if [ "${BACKUP_BEFORE_MIGRATE:-0}" = "1" ]; then
    echo "Sauvegarde avant migration..."
    "${PROJECT_ROOT}/bin/backup.sh"
else
    echo "AVERTISSEMENT : aucune sauvegarde n'est lancée avant la migration."
    echo "Pour en faire une : sudo ./bin/backup.sh"
    echo "Ou relancer avec BACKUP_BEFORE_MIGRATE=1."
fi

echo "Migrations Doctrine..."
cd "${BACKEND_DIR}"
run_as_web_user "${PHP_BIN}" bin/console doctrine:migrations:migrate \
    --no-interaction \
    --allow-no-migration \
    --env=prod \
    --no-debug

echo "Build frontend..."
cd "${BACKEND_DIR}"
rm -rf "${BACKEND_DIR}/public/build"
run_as_web_user "${NPM_BIN}" ci --no-audit --no-fund
run_as_web_user "${NPM_BIN}" run build

if [ ! -s "${BACKEND_DIR}/public/build/entrypoints.json" ]; then
    echo "ERREUR : public/build/entrypoints.json est absent ou vide."
    exit 1
fi

echo "Cache Symfony..."
cd "${BACKEND_DIR}"
run_as_web_user "${PHP_BIN}" bin/console cache:clear --env=prod --no-debug
run_as_web_user "${PHP_BIN}" bin/console cache:warmup --env=prod --no-debug
run_as_web_user "${PHP_BIN}" bin/console assets:install public --symlink --relative --env=prod --no-debug
run_as_web_user "${PHP_BIN}" bin/console lint:container --env=prod --no-debug

echo "Permissions finales..."
chown -R "${WEB_USER}:${WEB_GROUP}" \
    "${BACKEND_DIR}/var" \
    "${BACKEND_DIR}/public/build" \
    "${BACKEND_DIR}/public/uploads" \
    "${BACKEND_DIR}/vendor" \
    "${BACKEND_DIR}/node_modules"

find "${BACKEND_DIR}/var" "${BACKEND_DIR}/public/uploads" -type d -exec chmod 2775 {} +
find "${BACKEND_DIR}/var" "${BACKEND_DIR}/public/uploads" -type f -exec chmod 664 {} +
if [ -d "${BACKEND_DIR}/var/private" ]; then
    find "${BACKEND_DIR}/var/private" -type d -exec chmod 2770 {} +
    find "${BACKEND_DIR}/var/private" -type f -exec chmod 640 {} +
fi
chmod 640 "${BACKEND_DIR}/.env.local"
chown "${WEB_USER}:${WEB_GROUP}" "${BACKEND_DIR}/.env.local"

if [ ! -d "${BACKEND_DIR}/public/uploads/media" ]; then
    echo "ERREUR : le dossier des médias est absent."
    exit 1
fi

echo "Worker Messenger..."
cat > "${MESSENGER_SERVICE_FILE}" <<EOF
[Unit]
Description=Diaré Groupe Industrie - Symfony Messenger (${MESSENGER_SERVICE})
After=network.target mariadb.service
Wants=network.target

[Service]
Type=simple
User=${WEB_USER}
Group=${WEB_GROUP}
WorkingDirectory=${BACKEND_DIR}
Environment=APP_ENV=prod
Environment=APP_DEBUG=0
ExecStart=${PHP_BIN} bin/console messenger:consume async --time-limit=3600 --memory-limit=256M --sleep=1 --env=prod --no-debug -vv
Restart=always
RestartSec=5
TimeoutStopSec=20

[Install]
WantedBy=multi-user.target
EOF

chmod 644 "${MESSENGER_SERVICE_FILE}"
chown root:root "${MESSENGER_SERVICE_FILE}"
systemctl daemon-reload
systemctl enable "${MESSENGER_SERVICE}"
systemctl restart "${MESSENGER_SERVICE}"
sleep 2

if ! systemctl is-active --quiet "${MESSENGER_SERVICE}"; then
    echo "ERREUR : le worker Messenger n'est pas actif."
    echo "journalctl -u ${MESSENGER_SERVICE} -n 100 --no-pager"
    exit 1
fi

cd "${BACKEND_DIR}"
run_as_web_user "${PHP_BIN}" bin/console about --env=prod --no-debug >/dev/null
run_as_web_user "${PHP_BIN}" bin/console doctrine:migrations:status --env=prod --no-debug >/dev/null

if ! apache2ctl configtest; then
    echo "ERREUR : la configuration Apache est invalide. Aucun reload n'a été fait par ce script."
    exit 1
fi

if [ "${SKIP_HTTP_CHECK:-0}" = "1" ]; then
    echo "Contrôle HTTP ignoré (SKIP_HTTP_CHECK=1)."
else
    DEFAULT_URI="$(run_as_web_user "${PHP_BIN}" "${PROJECT_ROOT}/bin/lib/prod-env.php" print-uri)"
    echo "Contrôle HTTP : ${DEFAULT_URI}"

    home_code="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 20 "${DEFAULT_URI}/")"
    robots_code="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 20 "${DEFAULT_URI}/robots.txt")"
    sitemap_code="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 20 "${DEFAULT_URI}/sitemap.xml")"
    admin_code="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 20 "${DEFAULT_URI}/administration/connexion")"

    echo "Accueil ${home_code} ; robots ${robots_code} ; sitemap ${sitemap_code} ; admin ${admin_code}"

    if [ "${home_code}" != "200" ] || [ "${robots_code}" != "200" ] || [ "${sitemap_code}" != "200" ] || [ "${admin_code}" != "200" ]; then
        echo "ERREUR : le contrôle HTTP a échoué."
        echo "Avant le DNS, relancer avec SKIP_HTTP_CHECK=1."
        exit 1
    fi
fi

echo ""
echo "============================================================"
echo "Déploiement terminé"
echo "============================================================"
echo "Branche           : ${CURRENT_BRANCH}"
echo "Worker            : ${MESSENGER_SERVICE}"
echo "Médias            : ${BACKEND_DIR}/public/uploads/media"
echo "CV candidats      : ${BACKEND_DIR}/var/private/job-applications"
echo "Aucun cron métier : la purge des candidatures reste manuelle."
echo "============================================================"

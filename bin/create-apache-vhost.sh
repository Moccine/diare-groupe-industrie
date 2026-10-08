#!/usr/bin/env bash

# VirtualHost Apache isolé pour Diaré Groupe Industrie.
# Ne modifie aucun autre site. Ne recharge Apache qu'après un configtest réussi.
#
# Exemple :
#   sudo ./bin/create-apache-vhost.sh \
#       --domain diaregroupe.com \
#       --server-admin contact@diaregroupe.com \
#       --www
#
# HTTPS, uniquement quand le DNS pointe déjà vers ce VPS :
#   sudo ./bin/create-apache-vhost.sh --domain diaregroupe.com --server-admin contact@diaregroupe.com --www --ssl

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
DEFAULT_PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"

DOMAIN=""
PROJECT_ROOT="${DEFAULT_PROJECT_ROOT}"
SERVER_ADMIN=""
ENABLE_SSL=0
WITH_WWW=0
FORCE=0
ALIASES=()

WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
APACHE_SITES_AVAILABLE="/etc/apache2/sites-available"
APACHE_SERVICE="apache2"

TMP_VHOST=""
BACKUP_FILE=""
HEALTH_FILE=""

usage()
{
    cat <<EOF
Usage :
  sudo $0 --domain DOMAINE --server-admin EMAIL [options]

Options :
  --domain DOMAINE         Nom canonique. Obligatoire. Aucune valeur par défaut.
  --project-root CHEMIN    Racine du projet. Défaut : ${DEFAULT_PROJECT_ROOT}
  --server-admin EMAIL     ServerAdmin Apache. Obligatoire.
  --www                    Ajoute l'autre variante www / apex et la redirige vers --domain.
  --alias HÔTE             Alias supplémentaire. Répétable.
  --ssl                    Lance Certbot pour ce domaine seulement. À n'utiliser qu'une fois le DNS prêt.
  --force                  Réécrit le VirtualHost même si Certbot a déjà créé un certificat.
  --help
EOF
}

ensure_command()
{
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "ERREUR : la commande '$1' est absente."
        exit 1
    fi
}

cleanup()
{
    if [ -n "${TMP_VHOST}" ] && [ -f "${TMP_VHOST}" ]; then
        rm -f "${TMP_VHOST}"
    fi
    if [ -n "${HEALTH_FILE}" ] && [ -f "${HEALTH_FILE}" ]; then
        rm -f "${HEALTH_FILE}"
    fi
}

rollback_vhost()
{
    echo "Restauration du VirtualHost Diaré uniquement..."
    if [ -n "${BACKUP_FILE}" ] && [ -f "${BACKUP_FILE}" ]; then
        cp -a "${BACKUP_FILE}" "${VHOST_FILE}"
    else
        rm -f "${VHOST_FILE}"
        a2dissite "${SITE_CONFIG_NAME}" >/dev/null 2>&1 || true
    fi

    if apache2ctl configtest >/dev/null 2>&1; then
        systemctl reload "${APACHE_SERVICE}" || true
    fi
}

regex_escape()
{
    printf '%s' "$1" | sed -e 's/[][\\.^$*+?(){}|/]/\\&/g'
}

trap cleanup EXIT

while [ "$#" -gt 0 ]; do
    case "$1" in
        --domain)
            DOMAIN="${2:-}"
            [ -n "${DOMAIN}" ] || { echo "ERREUR : --domain nécessite une valeur."; exit 1; }
            shift 2
            ;;
        --project-root)
            PROJECT_ROOT="${2:-}"
            [ -n "${PROJECT_ROOT}" ] || { echo "ERREUR : --project-root nécessite une valeur."; exit 1; }
            shift 2
            ;;
        --server-admin)
            SERVER_ADMIN="${2:-}"
            [ -n "${SERVER_ADMIN}" ] || { echo "ERREUR : --server-admin nécessite une valeur."; exit 1; }
            shift 2
            ;;
        --alias)
            alias_value="${2:-}"
            if [ -z "${alias_value}" ]; then
                echo "ERREUR : --alias nécessite une valeur."
                exit 1
            fi
            ALIASES+=("${alias_value}")
            shift 2
            ;;
        --www)
            WITH_WWW=1
            shift
            ;;
        --ssl)
            ENABLE_SSL=1
            shift
            ;;
        --force)
            FORCE=1
            shift
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "ERREUR : option inconnue : $1"
            usage
            exit 1
            ;;
    esac
done

if [ "$(id -u)" -ne 0 ]; then
    echo "ERREUR : sudo $0 --domain DOMAINE --server-admin EMAIL"
    exit 1
fi

if [ -z "${DOMAIN}" ] || [ -z "${SERVER_ADMIN}" ]; then
    usage
    echo "ERREUR : --domain et --server-admin sont obligatoires."
    exit 1
fi

if [ "${WITH_WWW}" -eq 1 ]; then
    if [[ "${DOMAIN}" == www.* ]]; then
        ALIASES+=("${DOMAIN#www.}")
    else
        ALIASES+=("www.${DOMAIN}")
    fi
fi

ensure_command apache2ctl
ensure_command a2enmod
ensure_command a2ensite
ensure_command a2dissite
ensure_command systemctl
ensure_command curl
ensure_command install
ensure_command mktemp
ensure_command readlink

if [ "${ENABLE_SSL}" -eq 1 ]; then
    ensure_command certbot
fi

if ! [[ "${DOMAIN}" =~ ^[a-zA-Z0-9.-]+$ ]] || [[ "${DOMAIN}" != *.* ]]; then
    echo "ERREUR : domaine invalide."
    exit 1
fi

for alias in "${ALIASES[@]+"${ALIASES[@]}"}"; do
    if ! [[ "${alias}" =~ ^[a-zA-Z0-9.-]+$ ]]; then
        echo "ERREUR : alias invalide : ${alias}"
        exit 1
    fi
done

PROJECT_ROOT="$(readlink -f "${PROJECT_ROOT}")"
DOCUMENT_ROOT="${PROJECT_ROOT}/backend/public"
SITE_CONFIG_NAME="${DOMAIN}.conf"
VHOST_FILE="${APACHE_SITES_AVAILABLE}/${SITE_CONFIG_NAME}"
SSL_FILE="${APACHE_SITES_AVAILABLE}/${DOMAIN}-le-ssl.conf"
LOG_BASENAME="diare-groupe-industrie-${DOMAIN}"

if [ ! -f "${DOCUMENT_ROOT}/index.php" ]; then
    echo "ERREUR : DocumentRoot invalide : ${DOCUMENT_ROOT}"
    exit 1
fi

if ! systemctl is-active --quiet "${APACHE_SERVICE}"; then
    echo "ERREUR : Apache n'est pas actif."
    exit 1
fi

if [ -f "${VHOST_FILE}" ]; then
    existing_root="$(grep -E '^[[:space:]]*DocumentRoot' "${VHOST_FILE}" | head -1 | awk '{print $2}' | tr -d '"')"
    if [ -n "${existing_root}" ] && [ "${existing_root}" != "${DOCUMENT_ROOT}" ]; then
        echo "ERREUR : ${VHOST_FILE} appartient déjà à un autre site :"
        echo "  ${existing_root}"
        echo "Aucun VirtualHost n'a été modifié."
        exit 1
    fi
fi

if [ -f "${SSL_FILE}" ] && [ "${FORCE}" -ne 1 ]; then
    echo "ERREUR : ${SSL_FILE} existe déjà."
    echo "Relancer avec --force réécrirait le VirtualHost HTTP déjà relié à Certbot."
    echo "Aucun fichier n'a été modifié."
    exit 1
fi

ALIAS_LINE=""
HOST_REDIRECT=""
if [ "${#ALIASES[@]}" -gt 0 ]; then
    ALIAS_LINE="    ServerAlias ${ALIASES[*]}"
    DOMAIN_REGEX="$(regex_escape "${DOMAIN}")"
    HOST_REDIRECT="$(cat <<APACHE
        RewriteCond %{HTTP_HOST} !^${DOMAIN_REGEX}$ [NC]
        RewriteRule ^ http://${DOMAIN}%{REQUEST_URI} [R=301,L]

APACHE
)"
fi

echo "============================================================"
echo "VirtualHost Diaré"
echo "Domaine       : ${DOMAIN}"
echo "Aliases       : ${ALIASES[*]:-aucun}"
echo "DocumentRoot  : ${DOCUMENT_ROOT}"
echo "Configuration : ${VHOST_FILE}"
echo "Logs          : /var/log/apache2/${LOG_BASENAME}-error.log"
echo "SSL           : $([ "${ENABLE_SSL}" -eq 1 ] && echo oui || echo non)"
echo "============================================================"

TMP_VHOST="$(mktemp)"
cat > "${TMP_VHOST}" <<APACHE
<VirtualHost *:80>
    ServerName ${DOMAIN}
${ALIAS_LINE}
    ServerAdmin ${SERVER_ADMIN}

    DocumentRoot "${DOCUMENT_ROOT}"

    <Directory "${DOCUMENT_ROOT}">
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        DirectoryIndex index.php

        <IfModule mod_rewrite.c>
            RewriteEngine On
${HOST_REDIRECT}
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^ index.php [QSA,L]
        </IfModule>
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${LOG_BASENAME}-error.log
    CustomLog \${APACHE_LOG_DIR}/${LOG_BASENAME}-access.log combined
</VirtualHost>
APACHE

if [ -f "${VHOST_FILE}" ] && ! cmp -s "${TMP_VHOST}" "${VHOST_FILE}"; then
    BACKUP_FILE="${VHOST_FILE}.backup.$(date +%Y%m%d-%H%M%S)"
    cp -a "${VHOST_FILE}" "${BACKUP_FILE}"
    echo "Ancienne configuration Diaré sauvegardée : ${BACKUP_FILE}"
fi

install -o root -g root -m 0644 "${TMP_VHOST}" "${VHOST_FILE}"

a2enmod rewrite >/dev/null
a2ensite "${SITE_CONFIG_NAME}" >/dev/null

echo "Vérification Apache..."
if ! apache2ctl configtest; then
    echo "ERREUR : configuration Apache invalide. Les autres sites ne sont pas rechargés."
    rollback_vhost
    exit 1
fi

systemctl reload "${APACHE_SERVICE}"
echo "Apache rechargé. Les autres VirtualHosts n'ont pas été modifiés."

HEALTH_BASENAME="vhost-health-$RANDOM-$RANDOM.txt"
HEALTH_FILE="${DOCUMENT_ROOT}/${HEALTH_BASENAME}"
HEALTH_TOKEN="VHOST_OK_${DOMAIN}_$(date +%s)"
printf '%s\n' "${HEALTH_TOKEN}" > "${HEALTH_FILE}"
chown "${WEB_USER}:${WEB_GROUP}" "${HEALTH_FILE}"
chmod 644 "${HEALTH_FILE}"

HEALTH_RESPONSE="$(
    curl --fail --silent --show-error --max-time 15 \
        --resolve "${DOMAIN}:80:127.0.0.1" \
        "http://${DOMAIN}/${HEALTH_BASENAME}"
)"
rm -f "${HEALTH_FILE}"
HEALTH_FILE=""

if [ "${HEALTH_RESPONSE}" != "${HEALTH_TOKEN}" ]; then
    echo "ERREUR : le VirtualHost ne répond pas localement."
    exit 1
fi

echo "Test HTTP local : OK"

if [ "${ENABLE_SSL}" -eq 1 ]; then
    echo "Certbot pour ${DOMAIN} uniquement..."
    certbot_args=(--apache --non-interactive --agree-tos --redirect --keep-until-expiring --email "${SERVER_ADMIN}" -d "${DOMAIN}")
    for alias in "${ALIASES[@]+"${ALIASES[@]}"}"; do
        certbot_args+=(-d "${alias}")
    done
    certbot "${certbot_args[@]}"

    if ! apache2ctl configtest; then
        echo "ERREUR : Apache est invalide après Certbot. Aucun reload."
        exit 1
    fi
    systemctl reload "${APACHE_SERVICE}"
    echo "Certificat demandé. Le renouvellement reste celui du timer Certbot du système."
    echo "HSTS : à ajouter seulement après plusieurs jours de HTTPS stable."
fi

echo ""
echo "VirtualHost prêt : http://${DOMAIN}"
echo "DocumentRoot : ${DOCUMENT_ROOT}"
echo "Erreur : /var/log/apache2/${LOG_BASENAME}-error.log"
echo "Accès  : /var/log/apache2/${LOG_BASENAME}-access.log"

#!/usr/bin/env bash
#
# Exporte une copie légère du projet Diaré Groupe Industrie (Symfony + frontend)
# pour analyse (ChatGPT, etc.).
# Ne modifie JAMAIS le dossier source : copie sur le Bureau, nettoyage de la copie, zip.
#
# Usage :
#   ./scripts/export-site-public-chatgpt.sh
#   KEEP_FOLDER=1 ./scripts/export-site-public-chatgpt.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE="${SOURCE:-$(cd "$SCRIPT_DIR/.." && pwd -P)}"
DEST_DIR="${DEST_DIR:-$HOME/Desktop/diare-groupe-industrie-chatgpt}"
ZIP_PATH="${ZIP_PATH:-$HOME/Desktop/diare-groupe-industrie-chatgpt.zip}"
KEEP_FOLDER="${KEEP_FOLDER:-0}"

SOURCE="$(cd "$SOURCE" && pwd -P)"
DEST_DIR="$(cd "$(dirname "$DEST_DIR")" 2>/dev/null && pwd -P)/$(basename "$DEST_DIR")" || DEST_DIR="$HOME/Desktop/diare-groupe-industrie-chatgpt"
ZIP_PATH="$(cd "$(dirname "$ZIP_PATH")" 2>/dev/null && pwd -P)/$(basename "$ZIP_PATH")" || ZIP_PATH="$HOME/Desktop/diare-groupe-industrie-chatgpt.zip"

if [[ ! -d "$SOURCE" ]]; then
  echo "ERREUR : source introuvable : $SOURCE"
  exit 1
fi

if [[ "$DEST_DIR" == "$SOURCE" ]] || [[ "$DEST_DIR" == "$SOURCE/"* ]]; then
  echo "ERREUR : DEST_DIR ne peut pas etre dans SOURCE (risque sur le projet original)."
  exit 1
fi

if [[ "$ZIP_PATH" == "$SOURCE" ]] || [[ "$ZIP_PATH" == "$SOURCE/"* ]]; then
  echo "ERREUR : ZIP_PATH ne peut pas etre dans SOURCE."
  exit 1
fi

if [[ "$SOURCE" == "$HOME" ]] || [[ "$SOURCE" == "/" ]]; then
  echo "ERREUR : SOURCE trop large : $SOURCE"
  exit 1
fi

safe_rm() {
  local target="$1"
  case "$target" in
    "$SOURCE"|"$SOURCE"/*)
      echo "ERREUR : suppression interdite dans le projet source : $target"
      exit 1
      ;;
  esac
  rm -rf "$target"
}

echo "Source (lecture seule) : $SOURCE"
echo "Copie temporaire      : $DEST_DIR"
echo "Archive               : $ZIP_PATH"
echo ""

safe_rm "$DEST_DIR"
mkdir -p "$(dirname "$DEST_DIR")"

rsync -a \
  --exclude='.git/' \
  --exclude='.DS_Store' \
  --exclude='.idea/' \
  --exclude='.vscode/' \
  --exclude='node_modules/' \
  --exclude='backend/node_modules/' \
  --exclude='backend/vendor/' \
  --exclude='backend/var/' \
  --exclude='backend/.phpunit.cache/' \
  --exclude='backend/public/build/' \
  --exclude='backend/public/uploads/media/' \
  --exclude='public/build/' \
  --exclude='.env' \
  --exclude='.env.local' \
  --exclude='.env.*.local' \
  --exclude='backend/.env.local' \
  --exclude='backend/.env.*.local' \
  --exclude='docker-compose.override.yml' \
  --exclude='*.log' \
  --exclude='npm-debug.log*' \
  --exclude='yarn-debug.log*' \
  --exclude='yarn-error.log*' \
  "$SOURCE/" "$DEST_DIR/"

find "$DEST_DIR" -type f \( \
  -name '.env' -o -name '.env.local' -o -name '.env.*.local' \
\) -delete 2>/dev/null || true

HEAVY_DIRS=(
  "node_modules"
  "backend/node_modules"
  "backend/vendor"
  "backend/var"
  "backend/.phpunit.cache"
  "backend/public/build"
  "backend/public/uploads/media"
  "public/build"
  ".git"
)

for rel in "${HEAVY_DIRS[@]}"; do
  if [[ -e "$DEST_DIR/$rel" ]]; then
    safe_rm "$DEST_DIR/$rel"
  fi
done

mkdir -p "$DEST_DIR/backend/public/uploads/media"
touch "$DEST_DIR/backend/public/uploads/media/.gitkeep"

find "$DEST_DIR" -type f \( \
  -iname '*.zip' -o -iname '*.tar.gz' \
\) -delete 2>/dev/null || true

rm -f "$ZIP_PATH"
(
  cd "$(dirname "$DEST_DIR")"
  zip -r -q "$ZIP_PATH" "$(basename "$DEST_DIR")" -x "*.DS_Store"
)

SIZE_MB="$(du -sm "$ZIP_PATH" | cut -f1)"
echo "OK : $ZIP_PATH (${SIZE_MB} Mo)"
echo "Le projet original est intact : $SOURCE"
echo ""
echo "Contenu exporte : backend/ (sans vendor, var, build), frontend/, docker/, scripts/"
echo "Exclus : vendor, node_modules, cache Symfony, assets compiles, medias uploades, secrets (.env.local)"

if [[ "$KEEP_FOLDER" != "1" ]]; then
  safe_rm "$DEST_DIR"
  echo "Copie temporaire supprimee : $DEST_DIR"
else
  echo "Copie conservee : $DEST_DIR"
fi

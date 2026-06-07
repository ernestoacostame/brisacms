#!/usr/bin/env bash
# ============================================================================
# BrisaCMS · Release Script
# ============================================================================
# Uso:
#   ./release.sh 1.1.0              → release con notas vacías
#   ./release.sh 1.1.0 "Changelog"  → release con notas
#   ./release.sh patch               → auto-bump patch (1.0.0 → 1.0.1)
#   ./release.sh minor               → auto-bump minor (1.0.0 → 1.1.0)
#   ./release.sh major               → auto-bump major (1.0.0 → 2.0.0)
#
# Requisitos: git, gh (GitHub CLI autenticado)
# ============================================================================

set -euo pipefail

CMS_DIR="$(cd "$(dirname "$0")" && pwd)"
VERSION_FILE="$CMS_DIR/core/config.php"

# --- Leer versión actual ---
CURRENT=$(grep -oP "define\('CMS_VERSION',\s*'\K[0-9.]+" "$VERSION_FILE")
echo "📦 Versión actual: v$CURRENT"

# --- Calcular nueva versión ---
if [[ -z "${1:-}" ]]; then
  echo "Uso: $0 <version|patch|minor|major> [notas]"
  echo "  Ejemplos:"
  echo "    $0 patch              → $(echo "$CURRENT" | awk -F. '{print $1"."$2"."$3+1}')"
  echo "    $0 minor              → $(echo "$CURRENT" | awk -F. '{print $1"."$2+1".0"}')"
  echo "    $0 major              → $(echo "$CURRENT" | awk -F. '{print $1+1".0.0"}')"
  echo "    $0 1.1.0 \"Mi changelog\""
  exit 1
fi

INPUT="$1"
NOTES="${2:-}"

case "$INPUT" in
  patch) NEW=$(echo "$CURRENT" | awk -F. '{print $1"."$2"."$3+1}') ;;
  minor) NEW=$(echo "$CURRENT" | awk -F. '{print $1"."$2+1".0"}') ;;
  major) NEW=$(echo "$CURRENT" | awk -F. '{print $1+1".0.0"}') ;;
  *)     NEW="$INPUT" ;;
esac

echo "🚀 Nueva versión: v$NEW"
echo ""

# --- Confirmar ---
read -rp "¿Continuar? (s/N) " confirm
if [[ "$confirm" != "s" && "$confirm" != "S" ]]; then
  echo "Cancelado."
  exit 0
fi

# --- 1. Actualizar core/config.php ---
sed -i "s/define('CMS_VERSION', '$CURRENT')/define('CMS_VERSION', '$NEW')/" "$VERSION_FILE"
echo "✅ core/config.php actualizado a $NEW"

# --- 2. Crear el ZIP (solo ficheros de BrisaCMS, sin datos de usuario ni configs personales) ---
ZIP_NAME="brisacms-v${NEW}.zip"
ZIP_PATH="/tmp/$ZIP_NAME"

STAGE_DIR=$(mktemp -d)
cd "$CMS_DIR"

# Copiar sólo ficheros tracked por git (excluyendo carpetas dinámicas del usuario)
git ls-files -z \
  | grep -zZv -e '^content/' -e '^media/' -e '^uploads/' -e '^cache/' -e '^config.json' \
    -e '^\.installed' -e '^\.htaccess' -e '^release.\sh$' \
  | xargs -0 -I{} install -D "{}" "${STAGE_DIR}/{}"

# Crear ZIP
cd "$STAGE_DIR"
if command -v bsdtar &> /dev/null; then
  bsdtar -cf "$ZIP_PATH" --format=zip .
elif command -v zip &> /dev/null; then
  zip -r "$ZIP_PATH" .
else
  tar -cf - . | gzip > "$ZIP_PATH"
fi
rm -rf "$STAGE_DIR"

ZIP_SIZE=$(du -h "$ZIP_PATH" | cut -f1)
echo "✅ ZIP creado: $ZIP_NAME ($ZIP_SIZE)"

# --- 3. Git: commit + tag ---
cd "$CMS_DIR"

# Verificar si hay cambios staged/unstaged
UNSTAGED=$(git diff --name-only 2>/dev/null | grep -v 'core/config.php' | wc -l || true)

if [[ "$UNSTAGED" -gt 0 ]]; then
  echo "📋 Hay $UNSTAGED archivo(s) modificados sin stage."
  read -rp "¿Incluirlos en el release? (s/N) " inc
  if [[ "$inc" == "s" || "$inc" == "S" ]]; then
    git add .
  fi
fi

# Siempre incluir core/config.php
git add core/config.php

# Commit
git commit -m "Release BrisaCMS v$NEW"
git tag -a "v$NEW" -m "BrisaCMS v$NEW"
echo "✅ Commit y tag v$NEW creados"

# --- 4. Push ---
git push origin master
git push origin "v$NEW"
echo "✅ Pushed to GitHub"

# --- 5. Crear GitHub Release con el ZIP adjunto ---
if [[ -z "$NOTES" ]]; then
  PREV_TAG=$(git tag -l 'v*' --sort=-v:refname | grep -v "v$NEW" | head -1 || true)
  if [[ -n "$PREV_TAG" ]]; then
    NOTES=$(git log "${PREV_TAG}..v${NEW}" --oneline --no-merges 2>/dev/null | head -20 || true)
    if [[ -z "$NOTES" ]]; then
      NOTES="Release v$NEW"
    fi
  else
    NOTES="Release v$NEW"
  fi
fi

gh release create "v$NEW" "$ZIP_PATH" \
  --repo "ernestoacostame/BrisaCMS" \
  --title "BrisaCMS v$NEW" \
  --notes "$NOTES"

echo ""
echo "✅ Release v$NEW publicada con $ZIP_NAME adjunto"
echo "   https://github.com/ernestoacostame/BrisaCMS/releases/tag/v$NEW"

# --- Limpiar ---
rm -f "$ZIP_PATH"

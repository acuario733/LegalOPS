#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# deploy.sh — Script de deploy para LegalOPS Cloud V2
#
# USO:
#   ./scripts/deploy.sh [--env production|staging] [--skip-tests]
#
# QUÉ HACE:
#   1. Valida el entorno
#   2. Corre tests PHP (PHPUnit)
#   3. Compila assets JS/CSS (webpack production)
#      → webpack escribe el BUILD_HASH en storage/build_hash.txt
#   4. Corre migraciones de base de datos
#   5. Limpia cachés de la aplicación
#   6. Reporta éxito con el BUILD_HASH generado
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Colores para output ────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ── Defaults ──────────────────────────────────────────────────────────────────
APP_ENV="${DEPLOY_ENV:-production}"
SKIP_TESTS=false
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

# ── Parse args ────────────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case $1 in
        --env) APP_ENV="$2"; shift 2 ;;
        --skip-tests) SKIP_TESTS=true; shift ;;
        *) echo -e "${RED}Argumento desconocido: $1${NC}"; exit 1 ;;
    esac
done

cd "$ROOT_DIR"

echo -e "${BLUE}════════════════════════════════════════════${NC}"
echo -e "${BLUE}  LegalOPS Cloud V2 — Deploy ($APP_ENV)${NC}"
echo -e "${BLUE}════════════════════════════════════════════${NC}"

# ── 1. Validar entorno ────────────────────────────────────────────────────────
echo -e "\n${YELLOW}[1/6] Validando entorno...${NC}"

if [[ ! -f ".env" ]]; then
    echo -e "${RED}ERROR: No existe .env. Copiar de .env.example y configurar.${NC}"
    exit 1
fi

if ! command -v php &> /dev/null; then
    echo -e "${RED}ERROR: PHP no encontrado.${NC}"; exit 1
fi

if ! command -v composer &> /dev/null; then
    echo -e "${RED}ERROR: Composer no encontrado.${NC}"; exit 1
fi

if ! command -v npm &> /dev/null; then
    echo -e "${RED}ERROR: npm no encontrado.${NC}"; exit 1
fi

echo -e "${GREEN}✔ Entorno válido${NC}"

# ── 2. Instalar dependencias ──────────────────────────────────────────────────
echo -e "\n${YELLOW}[2/6] Instalando dependencias...${NC}"
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
npm ci --silent
echo -e "${GREEN}✔ Dependencias instaladas${NC}"

# ── 3. Tests PHP ──────────────────────────────────────────────────────────────
if [[ "$SKIP_TESTS" == "false" ]]; then
    echo -e "\n${YELLOW}[3/6] Corriendo tests PHP...${NC}"
    vendor/bin/phpunit --no-coverage
    echo -e "${GREEN}✔ Tests pasaron${NC}"
else
    echo -e "\n${YELLOW}[3/6] Tests omitidos (--skip-tests)${NC}"
fi

# ── 4. Compilar assets (webpack escribe BUILD_HASH) ───────────────────────────
echo -e "\n${YELLOW}[4/6] Compilando assets (producción)...${NC}"
npm run build 2>&1

# Verificar que webpack generó el hash
HASH_FILE="$ROOT_DIR/storage/build_hash.txt"
if [[ ! -f "$HASH_FILE" ]]; then
    echo -e "${RED}ERROR: webpack no generó storage/build_hash.txt${NC}"
    exit 1
fi

BUILD_HASH=$(cat "$HASH_FILE")
echo -e "${GREEN}✔ Assets compilados. BUILD_HASH = ${BUILD_HASH}${NC}"

# ── 5. Migraciones ────────────────────────────────────────────────────────────
echo -e "\n${YELLOW}[5/6] Corriendo migraciones...${NC}"
# Phinx migrations
if [[ -f "phinx.php" ]]; then
    vendor/bin/phinx migrate --environment "$APP_ENV" --configuration phinx.php
    echo -e "${GREEN}✔ Phinx migrations aplicadas${NC}"
fi
# Migraciones nativas del sistema (si existen)
if [[ -f "scripts/migrate.php" ]]; then
    php scripts/migrate.php
    echo -e "${GREEN}✔ Migraciones nativas aplicadas${NC}"
fi

# ── 6. Limpieza de caché ──────────────────────────────────────────────────────
echo -e "\n${YELLOW}[6/6] Limpiando cachés...${NC}"

# Limpiar caché de opcache si está disponible
if php -r "exit(function_exists('opcache_reset') ? 0 : 1);" 2>/dev/null; then
    php -r "opcache_reset();" || true
    echo -e "${GREEN}✔ OPcache limpiado${NC}"
fi

# Limpiar logs viejos (>30 días)
find "$ROOT_DIR/storage/logs" -name "*.log" -mtime +30 -delete 2>/dev/null || true

echo -e "${GREEN}✔ Caché limpiado${NC}"

# ── Resumen ───────────────────────────────────────────────────────────────────
echo -e "\n${GREEN}════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✔ Deploy completado exitosamente${NC}"
echo -e "${GREEN}  BUILD_HASH : ${BUILD_HASH}${NC}"
echo -e "${GREEN}  Entorno    : ${APP_ENV}${NC}"
echo -e "${GREEN}  Fecha      : $(date '+%Y-%m-%d %H:%M:%S')${NC}"
echo -e "${GREEN}════════════════════════════════════════════${NC}"

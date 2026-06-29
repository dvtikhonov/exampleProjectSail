#!/usr/bin/env bash
# Привести рабочую копию на VPS к origin/main перед деплоем.
#
# Типичная ситуация:
#   - локальные правки в тех же файлах, что уже в коммитах vps#5 / vps#7;
#   - мусор в main-app/bootstrap/* от старого mount bootstrap → bootstrap/cache;
#   - .bak и .env.save от ручных бэкапов.
#
# Запуск на VPS из корня репозитория:
#   ./scripts/vps-git-sync.sh          # только показать план
#   ./scripts/vps-git-sync.sh --apply  # сброс, очистка, pull, compose up

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

APPLY=0
if [[ "${1:-}" == "--apply" ]]; then
    APPLY=1
fi

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml:docker-compose.prod.yml}"
export COMPOSE_FILE

TRACKED_RESET=(
    docker-compose.yml
    docker-compose.prod.yml
    main-app/.env.example
    main-app/app/Providers/AppServiceProvider.php
    nginx-gateway/nginx.conf
    scripts/vps-nginx-ssl.sh
)

UNTRACKED_REMOVE=(
    deploy.yml.vps.bak
    docker-compose.prod.yml.vps.bak
    main-app/bootstrap/packages.php
    main-app/bootstrap/routes-v7.php
    main-app/bootstrap/services.php
    service-c/.env.save
    service-c/.env.save.1
)

echo "=== VPS git sync (apply=${APPLY}) ==="
echo "Каталог: ${ROOT_DIR}"
echo

echo "--- git status ---"
git status -sb
echo

BEHIND="$(git rev-list --count HEAD..origin/main 2>/dev/null || echo 0)"
echo "Отставание от origin/main: ${BEHIND} коммит(ов)"
if [[ "${BEHIND}" -gt 0 ]]; then
    echo "Ожидаемые коммиты (хвост):"
    git log HEAD..origin/main --oneline | head -5
fi
echo

echo "--- Сброс tracked (дубликаты vps#5) ---"
for f in "${TRACKED_RESET[@]}"; do
    if git diff --quiet -- "$f" 2>/dev/null; then
        echo "  OK (без изменений): $f"
    else
        echo "  RESET: $f"
        if [[ "${APPLY}" -eq 1 ]]; then
            git restore -- "$f"
        fi
    fi
done
echo

echo "--- Удаление untracked мусора ---"
for f in "${UNTRACKED_REMOVE[@]}"; do
    if [[ -e "$f" ]]; then
        echo "  REMOVE: $f"
        if [[ "${APPLY}" -eq 1 ]]; then
            rm -f -- "$f"
        fi
    else
        echo "  SKIP (нет файла): $f"
    fi
done
echo

if [[ "${APPLY}" -eq 0 ]]; then
    echo "Режим dry-run. Для применения:"
    echo "  ./scripts/vps-git-sync.sh --apply"
    exit 0
fi

echo "--- git pull --ff-only ---"
git fetch --all --prune
git pull --ff-only origin main

echo
echo "--- docker compose up ---"
docker compose up -d --remove-orphans

echo
echo "=== Готово ==="
git status -sb

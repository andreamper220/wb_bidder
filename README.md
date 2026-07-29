# WB Bidder

Symfony 7 + EasyAdmin bidder for Wildberries advertising.

## Quick start (Docker) — демо

```bash
docker compose build
docker compose up -d postgres redis
docker compose run --rm app composer install
docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm app php bin/console wb:seed-demo
docker compose run --rm app php bin/console wb:sync-stats
docker compose up -d
```

Admin: http://localhost:8080/admin — на панели есть **Демонстрационный стенд** (mock API, dry-run).

Tests:

```bash
docker compose run --rm app php bin/phpunit
```

## Реальные кампании

Проект можно использовать для боевого управления ставками поисковых кластеров Wildberries.

1. Скопируйте `docker-compose.override.example.yml` → `docker-compose.override.yml`
2. Задайте `WB_API_MOCK=0`, `WB_API_KEY`, `APP_SECRET`
3. Запустите воркеры: `docker compose up -d worker-sync worker-bidding worker-execution`
4. Создайте кампанию в админке, пройдите sync → dry-run → боевой запуск

**Полная инструкция:** [docs/PRODUCTION.md](docs/PRODUCTION.md)

Чеклист готовности отображается на панели админки в блоке «Реальные кампании».

## Architecture

See `ARCHITECTURE.md` and `docs/BIDDING_STRATEGY.md`.

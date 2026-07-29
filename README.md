# WB Bidder

Symfony 7 + EasyAdmin bidder for Wildberries advertising.

## Quick start (Docker)

```bash
docker compose build
docker compose up -d postgres redis
docker compose run --rm app composer install
docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm app php bin/console wb:seed-demo
docker compose run --rm app php bin/console wb:sync-stats
docker compose up -d
```

Admin: http://localhost:8080/admin

Tests:

```bash
docker compose run --rm app php bin/phpunit
```

## Architecture

See `ARCHITECTURE.md` and `docs/BIDDING_STRATEGY.md`.

#!/bin/sh
set -e

docker compose build app
docker compose up -d postgres redis
docker compose run --rm app composer install --no-interaction
docker compose run --rm app php bin/console doctrine:database:create --if-not-exists --no-interaction
docker compose run --rm app php bin/console doctrine:database:create --if-not-exists --env=test --no-interaction
docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction --env=test
docker compose run --rm -e APP_ENV=test app php bin/phpunit
docker compose run --rm app php bin/console wb:seed-demo
docker compose run --rm app php bin/console wb:sync-stats
echo "OK: build, migrations, tests, seed completed"

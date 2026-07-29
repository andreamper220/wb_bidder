# WB Bidder

Система автоматического выставления и корректировки ставок в рекламных кампаниях Wildberries.
Управление ставками поисковых кластеров без ML-оптимизации.

Symfony 7.2 · PHP 8.4 · PostgreSQL 16 · Redis · EasyAdmin

## Ответы на тестовое задание

| # | Документ | Вопрос |
|---|---|---|
| 1 | [docs/01-TECHNICAL-SPEC.md](docs/01-TECHNICAL-SPEC.md) | Техническое задание и архитектура |
| 2 | [docs/02-AI-WORKFLOW.md](docs/02-AI-WORKFLOW.md) | Подход к реализации через AI: правила и гейты |
| 3 | [docs/03-AI-REVIEW.md](docs/03-AI-REVIEW.md) | Где я проверяю AI и что отклоняю |

Прототип в этом репозитории — рабочая иллюстрация к первому документу и материал для третьего:
ревизия собственного кода дала 23 задокументированных дефекта
([docs/KNOWN-ISSUES.md](docs/KNOWN-ISSUES.md)), из них три критических.

Полный указатель документации — [docs/README.md](docs/README.md).

## Что делает система

Контур управления с обратной связью: читает статистику из WB API, сравнивает эффективность с
экономическими порогами селлера, делает ограниченный шаг ставки в нужную сторону.

| Уровень | Метрика | Источник | Результат |
|---|---|---|---|
| Кампания | ROAS | `GET /adv/v3/fullstats` | режим DEFENSIVE / BALANCED / GROWTH |
| Кластер (`norm_query`) | CPA | `POST /adv/v0/normquery/stats` | UP / DOWN / HOLD / PAUSE |

Режим кампании ограничивает только рост ставки: снижение и пауза не блокируются ничем.
Дальше решение проходит цепочку предохранителей и записывается в журнал вместе с причиной — по
каждому изменению ставки видно, на каких данных и по какому правилу оно принято.

## Быстрый старт (демо на моках)

```bash
docker compose build
docker compose up -d postgres redis
docker compose run --rm app composer install
docker compose run --rm app php bin/console doctrine:migrations:migrate --no-interaction
docker compose run --rm app php bin/console wb:demo-stand --reset
docker compose up -d
```

Админка: http://localhost:8080/admin — на панели есть **Демонстрационный стенд** (mock API, dry-run).
Демо показывает ключевой сценарий: кластер с хорошей CPA предлагает `UP`, но защитный режим
кампании по ROAS превращает его в `HOLD`.

## Проверки

```bash
docker compose run --rm app php bin/phpunit         # тесты
docker compose run --rm app bash scripts/gates.sh   # гейт G0 целиком
bash scripts/gates.sh --no-tests                    # быстрая проверка паттернов без PHP
```

## Реальные кампании

Пошаговая процедура — [docs/PRODUCTION.md](docs/PRODUCTION.md).

> Перед боевым запуском обязательно прочитать [docs/KNOWN-ISSUES.md](docs/KNOWN-ISSUES.md):
> в прототипе есть блокирующие дефекты, включая неподтверждённые единицы измерения ставки
> при записи в WB. Ошибка в них означает расход в 100 раз выше расчётного.

## Структура

```
src/WbApi/      адаптер WB API, DTO, маппер, mock-провайдер
src/Sync/       синхронизация статистики и текущих ставок
src/Metrics/    агрегация окна, ROAS/CPA, режим кампании
src/Bidding/    стратегия, слияние, предохранители, пайплайн
src/Service/    применение решений в WB
src/Admin/      панель, чеклист готовности, демо-стенд
docs/           документация (см. docs/README.md)
.cursor/rules/  правила для AI-агента
scripts/        гейты и вспомогательные скрипты
```

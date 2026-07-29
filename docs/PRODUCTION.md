# Подключение к реальным кампаниям Wildberries

Ядро пайплайна (синхронизация → ROAS/CPA-стратегия → предохранители → отправка ставок) работает;
ниже — процедура подключения реальной кампании.

> **Перед боевым запуском.** Открытый блокирующий дефект: админка без аутентификации
> ([KI-09](KNOWN-ISSUES.md#ki-09)). Запись ставок — через `POST /api/advert/v1/normquery/bids`
> (`bidMinorUnits`, копейки). Инструкция ниже пригодна для dry-run и канарейки после закрытия KI-09.

## Что поддерживается

| Уровень | Метрика | Источник WB API | Действие |
|---------|---------|-----------------|----------|
| Кампания | ROAS | `GET /adv/v3/fullstats` | Режим DEFENSIVE / BALANCED / GROWTH |
| Кластер | CPA | `POST /adv/v0/normquery/stats` | UP / DOWN / HOLD / PAUSE |
| Кластер | Текущая ставка | `POST /adv/v0/normquery/get-bids` | Синхронизация `current_bid_kopecks` |
| Исполнение | — | `POST /api/advert/v1/normquery/bids` | Установка ставок (`bidMinorUnits`) |
| Пауза | — | `DELETE /adv/v0/normquery/bids` | Сброс кластерной ставки |

**Не поддерживается:** ставки на уровне отдельных поисковых фраз — ограничение WB API.

## Предварительные требования

1. Рекламная кампания в кабинете WB с типом, поддерживающим кластеры `norm_query`.
2. Токен API продвижения (раздел «Настройки → Доступ к API» в кабинете продавца).
3. Docker (или PHP 8.3+, PostgreSQL 16, Redis 7).
4. Запущенные воркеры очередей (`worker-sync`, `worker-bidding`, `worker-execution`).

## Шаг 1. Переменные окружения

Создайте `.env.local` (не коммитить в git):

```bash
# Отключить mock — обязательно для боевого режима
WB_API_MOCK=0

# Токен API продвижения WB
WB_API_KEY=ваш_токен_здесь

# Секрет Symfony (сгенерировать: openssl rand -hex 32)
APP_SECRET=случайная_строка_64_символа

# Базовый URL API (обычно менять не нужно)
WB_API_BASE_URL=https://advert-api.wildberries.ru
```

### Docker

Скопируйте пример и отредактируйте:

```bash
cp docker-compose.override.example.yml docker-compose.override.yml
```

В `docker-compose.override.yml` задайте `WB_API_MOCK: "0"` и `WB_API_KEY` для сервисов `app` и всех `worker-*`.

Перезапустите контейнеры:

```bash
docker compose up -d
```

## Шаг 2. Запуск воркеров

Пайплайн асинхронный — без воркеров синхронизация и отправка ставок не выполнятся:

```bash
docker compose up -d worker-sync worker-bidding worker-execution
```

Проверка:

```bash
docker compose ps
docker compose logs -f worker-sync
```

## Шаг 3. Добавление кампании

1. Откройте админку: http://localhost:8080/admin
2. **Кампании → + Новая кампания**
3. Заполните:
   - **WB ID** — `advertId` из кабинета WB (число из URL или списка кампаний)
   - **Seed nmId** — артикул товара из кампании (нужен для первого sync, пока кластеров ещё нет)
   - **Название** — произвольное имя для навигации
   - **Активна** — включено
   - **Автобиддинг** — выключено на первом этапе
   - **Dry-run** — **включено** (обязательно для первой проверки)
4. Настройте пороги ROAS и CPA под вашу нишу (см. [BIDDING_STRATEGY.md](BIDDING_STRATEGY.md))

## Шаг 4. Синхронизация статистики

```bash
docker compose exec app php bin/console wb:sync-stats
```

Что происходит:
- Запрос `fullstats` и `normquery/stats` к WB API
- Создание/обновление кластеров (`norm_query`) в БД
- Запрос `normquery/get-bids` — подтягивание текущих ставок из WB в `current_bid_kopecks`
- Автоматическая постановка задачи `RunBiddingMessage` в очередь `wb_bidding`

Проверьте в админке: у кампании появились кластеры, на панели — метрики ROAS/расход. Ставки кластеров должны совпадать с кабинетом WB (в копейках).

## Шаг 5. Проверка в dry-run

```bash
# Включите автобиддинг и dry-run в карточке кампании, затем:
docker compose exec app php bin/console wb:run-bidding --dry-run
```

Или дождитесь автоматического расчёта после sync (воркер `worker-bidding`).

Проверьте **История ставок**:
- `proposal` — что предложила CPA-стратегия
- `final` — итог после режима ROAS и предохранителей
- `status` — `skipped` (dry-run) или `pending` → `applied`

Убедитесь, что решения соответствуют ожиданиям: нет неожиданных UP в DEFENSIVE, ставки в пределах min/max.

## Шаг 6. Боевой запуск

Только после успешной проверки dry-run:

1. В карточке кампании: **Dry-run → выключить**
2. **Автобиддинг → включить**
3. Запустите расчёт:

```bash
docker compose exec app php bin/console wb:run-bidding
```

Решения со статусом `pending` попадут в очередь `wb_execution`; воркер отправит ставки в WB.

## Шаг 7. Регулярное расписание (cron)

Автоматизация вне демо-режима — через cron на хосте или планировщик:

```cron
# Синхронизация каждые 2 часа (цепочка sync → bidding → execution через воркеры)
0 */2 * * * cd /path/to/wb_bidder && docker compose exec -T app php bin/console wb:sync-stats
```

Интервал подберите под объём кампаний и лимиты WB API. Команда `wb:sync-stats` ставит sync для всех кампаний; bidding запускается автоматически после sync.

## Чеклист готовности

| # | Пункт | Как проверить |
|---|-------|---------------|
| 1 | `WB_API_MOCK=0` | Бейдж на панели админки без «WB_API_MOCK=1» |
| 2 | `WB_API_KEY` задан | Панель → блок «Реальные кампании» |
| 3 | `APP_SECRET` изменён | Не `change_me_in_prod` / `docker_secret_change_me` |
| 4 | Воркеры запущены | `docker compose ps` — 3 worker-* в Up |
| 5 | Кампания создана | WB advert ID ≠ 100001 (демо) |
| 6 | Sync выполнен | Кластеры > 0 у кампании |
| 7 | Dry-run проверен | История ставок, логика UP/HOLD корректна |
| 8 | Боевой режим | dry-run OFF, автобиддинг ON |

Чеклист также отображается на **Панели** в блоке «Реальные кампании».

## Ограничения и риски

Блокирующие боевой запуск:

| Риск | Дефект |
|---|---|
| Админка без аутентификации: любой может начать тратить деньги | [KI-09](KNOWN-ISSUES.md#ki-09) |

Функциональные ограничения:

- **Только CPM-кампании с ручной ставкой** — кластерные ставки для других типов недоступны на
  стороне WB.
- **Кластеры без ставки в ответе `get-bids`** сохраняют прежнее значение в БД, то есть могут
  расходиться с кабинетом.
- **Ставки на отдельные поисковые фразы не поддерживаются** — ограничение WB API.
- Откат ставок: `wb:restore-bids --campaign=ID` (по умолчанию dry-run; `--apply` пишет в WB).

Лимиты WB API и расчёт достижимой частоты обновления — [WB-API-LIMITS.md](WB-API-LIMITS.md).

## Мониторинг

- **История ставок** — все решения с proposal/final/status
- **Логи воркеров:** `docker compose logs -f worker-execution`
- **Failed queue:** `php bin/console messenger:failed:show`

## Откат к демо

Для тестирования без риска верните `WB_API_MOCK=1` и используйте **Демонстрационный стенд** на панели.

## См. также

- [BIDDING_STRATEGY.md](BIDDING_STRATEGY.md) — спецификация алгоритма
- [WB-API-LIMITS.md](WB-API-LIMITS.md) — лимиты API и расчёт частоты обновления
- [KNOWN-ISSUES.md](KNOWN-ISSUES.md) — что мешает боевому запуску
- [QUEUES.md](QUEUES.md) — очереди и воркеры
- [01-TECHNICAL-SPEC.md](01-TECHNICAL-SPEC.md) — техническое задание

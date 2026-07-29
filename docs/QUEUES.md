# Очереди и воркеры

Транспорт — Redis Streams через Symfony Messenger. Конфигурация: `config/packages/messenger.yaml`,
воркеры — сервисы `worker-*` в `docker-compose.yml`.

## Зачем три очереди

Разделение не по «типам задач», а по трём свойствам, которые у этапов различаются принципиально:
**лимит внешнего API, последствия отказа и безопасность повтора**.

| Очередь | Лимит WB API | Последствие отказа | Безопасность повтора |
|---|---|---|---|
| `wb_sync` | 3–10 запросов/мин — самая медленная | данные устарели, решения не принимаются | полная: чтение идемпотентно |
| `wb_bidding` | нет, только CPU и БД | решения не рассчитаны | полная: чистый расчёт |
| `wb_execution` | запись ставок (v1) | **ставка не применена** | требует осторожности |

Если объединить их в одну очередь, медленное чтение статистики (ожидание квоты `fullstats` — до
20 секунд между запросами) будет блокировать применение уже принятых решений. Разделение также даёт
раздельные DLQ: разбор «не смогли прочитать статистику» и «не смогли изменить ставку» — это разные
инциденты с разной срочностью.

## Конвейер

```
Планировщик / cron
  └─ SyncCampaignStatsMessage  → wb_sync
       ├─ GET  /adv/v3/fullstats                 → campaign_daily_stats
       ├─ POST /adv/v0/normquery/stats           → clusters + cluster_daily_stats
       ├─ POST /adv/v0/normquery/get-bids        → clusters.current_bid_kopecks
       └─ RunBiddingMessage    → wb_bidding
            ├─ BiddingPipeline (без обращений к WB)
            └─ ApplyBidDecisionMessage (на каждое решение в статусе pending) → wb_execution
                 ├─ POST /api/advert/v1/normquery/bids   (UP/DOWN/resume)
                 └─ DELETE /adv/v0/normquery/bids        (PAUSE)
```

В dry-run цепочка обрывается на `wb_bidding`: решения записываются со статусом `skipped` и
сообщения на исполнение не отправляются.

## Воркеры

```bash
docker compose up -d worker-sync worker-bidding worker-execution
docker compose logs -f worker-execution
```

Каждый воркер запускается как `messenger:consume <transport> -vv --time-limit=3600` с
`restart: unless-stopped`: перезапуск раз в час освобождает память и подхватывает изменения кода.

Горизонтальное масштабирование `worker-sync` **не ускоряет** синхронизацию: квота WB общая на
аккаунт, поэтому больше воркеров означает больше 429, а не больше данных. Ускоряется синхронизация
батчированием в `WbStatsSyncService::syncAll()` и token bucket в `config/packages/rate_limiter.yaml`.

## Идемпотентность

| Этап | Что делает повтор безопасным |
|---|---|
| Синхронизация статистики | уникальные ключи `(campaign_id, date)` и `(cluster_id, date)` — повторная запись обновляет ту же строку |
| Расчёт решений | чистая функция от состояния БД; повтор создаёт новые записи решений, но не меняет ставки |
| Применение ставки | в WB отправляется **абсолютное** значение, а не дельта; статус меняется `pending → applied`, повторная доставка сообщения пропускается по статусу |

Выбор абсолютного значения вместо дельты — ключевое решение: оно снимает требование exactly-once,
которого очереди не дают.

## Обработка ошибок

| | Есть | Ещё желательно |
|---|---|---|
| DLQ | `failure_transport: failed` | алерт на непустой DLQ |
| Повторы | `retry_strategy` на транспортах | jitter / уважение `Retry-After` |
| 429 | адаптер бросает; rate limiter ждёт квоту до запроса | отдельный счётчик и circuit breaker |
| Ограничение частоты | Symfony RateLimiter + Redis (`cache.rate_limiter`) | ключ с `account_id` при мультиаккаунтности |

## Диагностика

```bash
php bin/console messenger:stats           # длины очередей
php bin/console messenger:failed:show     # содержимое DLQ
php bin/console messenger:failed:retry    # повтор вручную
```

Пустые очереди при живых воркерах — не признак здоровья. Может означать, что задачи вообще не
ставятся (не работает cron или планировщик) либо все ушли в DLQ. Отсюда требование алерта
«система ничего не делает» из [§12 ТЗ](01-TECHNICAL-SPEC.md#12-наблюдаемость).

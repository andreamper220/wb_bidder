# Architecture

## Two-level strategy (Variant B)

1. **Level 1 — Campaign ROAS** (`fullstats`, exact revenue) → mode DEFENSIVE / BALANCED / GROWTH
2. **Level 2 — Cluster CPA** (`normquery/stats`) → proposal up/down/hold/pause
3. **Merge** — DEFENSIVE blocks UP proposals
4. **Guards** — min/max bid, cooldown (Chain of Responsibility)
5. **Execution** — WB API set bids (`wb_execution` queue)

## Patterns

| Pattern | Location |
|---------|----------|
| Adapter | `WbPromotionApiAdapter`, `WbApiResponseMapper` |
| DTO | `src/WbApi/Dto/` |
| Strategy | `ClusterCpaStrategy` |
| Pipeline | `BiddingPipeline` |
| Chain of Responsibility | `BidGuardChain` |
| Command | `src/Message/`, `src/Command/` |
| Repository | `src/Repository/` |

## Queues

- `wb_sync` — fetch stats + current bids from WB API
- `wb_bidding` — calculate decisions
- `wb_execution` — apply bids to WB

## Granularity

- Campaign — ROAS (Level 1)
- Cluster — CPA (Level 2)
- Phrase — **not supported by WB API** (disabled in UI)

## Production

See [docs/PRODUCTION.md](docs/PRODUCTION.md) for real campaign setup (API keys, workers, dry-run → live).

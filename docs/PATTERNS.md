# Architectural patterns

| Pattern | Implementation |
|---------|----------------|
| **Adapter** | `WbPromotionApiAdapter` wraps HttpClient + mock mode |
| **DTO** | `src/WbApi/Dto/*` |
| **Strategy** | `ClusterCpaStrategy` (Level 2 CPA proposals) |
| **Pipeline** | `BiddingPipeline` — aggregate → mode → propose → merge → guard |
| **Chain of Responsibility** | `BidGuardChain` + `MinMaxBidGuard`, `CooldownGuard` |
| **Command** | Messenger messages + Console commands |
| **Repository** | Doctrine repositories in `src/Repository/` |

## Two-level bidding

1. `CampaignModeResolver` — Level 1 ROAS → DEFENSIVE / BALANCED / GROWTH
2. `ClusterCpaStrategy` — Level 2 CPA proposal
3. `CampaignModeMerger` — filter proposal by mode

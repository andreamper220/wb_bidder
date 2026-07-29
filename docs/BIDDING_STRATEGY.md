# Bidding strategy

## Level 1 — Campaign (ROAS)

ROAS = revenue / spend from `GET /adv/v3/fullstats`.

| Mode | When |
|------|------|
| DEFENSIVE | ROAS < `restrict_up_if_roas_below` |
| BALANCED | between thresholds |
| GROWTH | ROAS ≥ `allow_up_if_roas_above` |

## Level 2 — Cluster (CPA)

CPA = spend / orders from `POST /adv/v1/normquery/stats`.

| CPA vs target | Proposal |
|---------------|----------|
| CPA > target + buffer | DOWN |
| CPA < target | UP |
| CPA = target | HOLD |

## Merge rules

| Proposal | DEFENSIVE | BALANCED / GROWTH |
|----------|-----------|-------------------|
| UP | HOLD | UP |
| DOWN | DOWN | DOWN |
| HOLD | HOLD | HOLD |
| PAUSE | PAUSE | PAUSE |

## Phrase granularity

Not supported by WB API. Use search clusters (`norm_query`).

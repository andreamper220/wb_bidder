# Queues

## wb_sync

Loads campaign and cluster statistics from WB API. Rate-limited, slow.

## wb_bidding

Aggregates metrics and runs `BiddingPipeline`. No WB write calls.

## wb_execution

Applies pending `bid_decisions` via WB API.

Separation avoids slow sync blocking bid calculation, and isolates retry/DLQ for API writes.

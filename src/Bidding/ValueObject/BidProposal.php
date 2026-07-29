<?php

namespace App\Bidding\ValueObject;

use App\Enum\BidAction;

/**
 * Proposal from Level 2 (CPA cluster) before campaign mode merge.
 */
final readonly class BidProposal
{
    public function __construct(
        public BidAction $action,
        public string $reason,
    ) {
    }
}

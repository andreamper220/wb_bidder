<?php

namespace App\Bidding\ValueObject;

use App\Enum\BidAction;

/**
 * Final action after Level 1 mode filter and optional guards metadata.
 */
final readonly class BidIntent
{
    public function __construct(
        public BidAction $action,
        public string $reason,
        public ?BidAction $originalProposal = null,
        public ?string $modeFilterReason = null,
    ) {
    }
}

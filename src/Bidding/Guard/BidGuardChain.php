<?php

namespace App\Bidding\Guard;

use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;

/**
 * Pattern: Chain of Responsibility.
 */
final class BidGuardChain
{
    /** @param iterable<BidGuardInterface> $guards */
    public function __construct(private readonly iterable $guards)
    {
    }

    public function apply(Campaign $campaign, Cluster $cluster, BidIntent $intent, int $proposedBidKopecks): BidIntent
    {
        if ($intent->action === BidAction::Hold || $intent->action === BidAction::Pause) {
            return $intent;
        }

        foreach ($this->guards as $guard) {
            $reason = $guard->check($campaign, $cluster, $intent, $proposedBidKopecks);
            if ($reason !== null) {
                return new BidIntent(BidAction::Hold, $reason, $intent->originalProposal, $intent->modeFilterReason);
            }
        }

        return $intent;
    }
}

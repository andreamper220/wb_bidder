<?php

namespace App\Bidding\Guard;

use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;

final class MinMaxBidGuard implements BidGuardInterface
{
    public function check(
        Campaign $campaign,
        Cluster $cluster,
        BidIntent $intent,
        int $proposedBidKopecks,
        \DateTimeImmutable $now,
    ): ?string {
        if ($intent->action === BidAction::Hold || $intent->action === BidAction::Pause) {
            return null;
        }

        if ($proposedBidKopecks < $campaign->getMinBidKopecks()) {
            return 'below_min_bid';
        }

        if ($proposedBidKopecks > $campaign->getMaxBidKopecks()) {
            return 'above_max_bid';
        }

        return null;
    }
}

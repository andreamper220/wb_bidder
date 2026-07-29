<?php

namespace App\Bidding\Guard;

use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;

final class DeadBandGuard implements BidGuardInterface
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

        $old = $cluster->getCurrentBidKopecks();
        if ($old <= 0) {
            return null;
        }

        $delta = abs($proposedBidKopecks - $old);
        $minDelta = intdiv($old * $campaign->getMinChangePct(), 100);
        if ($delta < $minDelta) {
            return 'below_dead_band';
        }

        return null;
    }
}

<?php

namespace App\Bidding\Guard;

use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;

final class CooldownGuard implements BidGuardInterface
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

        $lastChange = $cluster->getLastBidChangeAt();
        if ($lastChange === null) {
            return null;
        }

        $cooldownEnds = $lastChange->modify(sprintf('+%d hours', $campaign->getCooldownHours()));
        if ($cooldownEnds > $now) {
            return 'cooldown_active';
        }

        return null;
    }
}

<?php

namespace App\Bidding\Guard;

use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;

final class CooldownGuard implements BidGuardInterface
{
    public function check(Campaign $campaign, Cluster $cluster, BidIntent $intent, int $proposedBidKopecks): ?string
    {
        if ($intent->action === BidAction::Hold) {
            return null;
        }

        $lastChange = $cluster->getLastBidChangeAt();
        if ($lastChange === null) {
            return null;
        }

        $cooldownEnds = $lastChange->modify(sprintf('+%d hours', $campaign->getCooldownHours()));
        if ($cooldownEnds > new \DateTimeImmutable()) {
            return 'cooldown_active';
        }

        return null;
    }
}

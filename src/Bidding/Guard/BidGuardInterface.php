<?php

namespace App\Bidding\Guard;

use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;

interface BidGuardInterface
{
    public function check(
        Campaign $campaign,
        Cluster $cluster,
        BidIntent $intent,
        int $proposedBidKopecks,
        \DateTimeImmutable $now,
    ): ?string;
}

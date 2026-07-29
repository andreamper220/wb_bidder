<?php

namespace App\Tests\Unit\Bidding;

use App\Bidding\Guard\MinMaxBidGuard;
use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;
use PHPUnit\Framework\TestCase;

final class MinMaxBidGuardTest extends TestCase
{
    public function testBelowMinBid(): void
    {
        $campaign = new Campaign(1);
        $campaign->setMinBidKopecks(5000);
        $campaign->setMaxBidKopecks(50000);
        $cluster = new Cluster($campaign, 1, 'q', 5500);
        $intent = new BidIntent(BidAction::Down, 'cpa_above_target');
        $now = new \DateTimeImmutable('2026-07-29 12:00:00');

        $reason = (new MinMaxBidGuard())->check($campaign, $cluster, $intent, 4675, $now);

        $this->assertSame('below_min_bid', $reason);
    }

    public function testAboveMaxBid(): void
    {
        $campaign = new Campaign(1);
        $campaign->setMinBidKopecks(5000);
        $campaign->setMaxBidKopecks(50000);
        $cluster = new Cluster($campaign, 1, 'q', 48000);
        $intent = new BidIntent(BidAction::Up, 'cpa_below_target');
        $now = new \DateTimeImmutable('2026-07-29 12:00:00');

        $reason = (new MinMaxBidGuard())->check($campaign, $cluster, $intent, 55200, $now);

        $this->assertSame('above_max_bid', $reason);
    }

    public function testWithinBoundsPasses(): void
    {
        $campaign = new Campaign(1);
        $campaign->setMinBidKopecks(5000);
        $campaign->setMaxBidKopecks(50000);
        $cluster = new Cluster($campaign, 1, 'q', 10000);
        $intent = new BidIntent(BidAction::Up, 'cpa_below_target');
        $now = new \DateTimeImmutable('2026-07-29 12:00:00');

        $reason = (new MinMaxBidGuard())->check($campaign, $cluster, $intent, 11000, $now);

        $this->assertNull($reason);
    }
}

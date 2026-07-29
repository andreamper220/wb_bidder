<?php

namespace App\Tests\Unit\Bidding;

use App\Bidding\Guard\DeadBandGuard;
use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;
use PHPUnit\Framework\TestCase;

final class DeadBandGuardTest extends TestCase
{
    public function testBlocksTinyChange(): void
    {
        $campaign = new Campaign(1);
        $campaign->setMinChangePct(3);
        $cluster = new Cluster($campaign, 1, 'q', 10000);
        $intent = new BidIntent(BidAction::Up, 'cpa_below_target');
        $now = new \DateTimeImmutable('2026-07-29 12:00:00');

        $reason = (new DeadBandGuard())->check($campaign, $cluster, $intent, 10200, $now);

        $this->assertSame('below_dead_band', $reason);
    }

    public function testAllowsLargeEnoughChange(): void
    {
        $campaign = new Campaign(1);
        $campaign->setMinChangePct(3);
        $cluster = new Cluster($campaign, 1, 'q', 10000);
        $intent = new BidIntent(BidAction::Up, 'cpa_below_target');
        $now = new \DateTimeImmutable('2026-07-29 12:00:00');

        $reason = (new DeadBandGuard())->check($campaign, $cluster, $intent, 10400, $now);

        $this->assertNull($reason);
    }
}

<?php

namespace App\Tests\Unit\Bidding;

use App\Bidding\Guard\CooldownGuard;
use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;
use PHPUnit\Framework\TestCase;

final class CooldownGuardTest extends TestCase
{
    public function testBlocksWhenCooldownActive(): void
    {
        $campaign = new Campaign(1);
        $campaign->setCooldownHours(6);
        $cluster = new Cluster($campaign, 1, 'q', 10000);
        $cluster->setLastBidChangeAt(new \DateTimeImmutable('2026-07-29 10:00:00'));
        $intent = new BidIntent(BidAction::Up, 'cpa_below_target');
        $now = new \DateTimeImmutable('2026-07-29 12:00:00');

        $reason = (new CooldownGuard())->check($campaign, $cluster, $intent, 11000, $now);

        $this->assertSame('cooldown_active', $reason);
    }

    public function testAllowsWhenCooldownExpired(): void
    {
        $campaign = new Campaign(1);
        $campaign->setCooldownHours(6);
        $cluster = new Cluster($campaign, 1, 'q', 10000);
        $cluster->setLastBidChangeAt(new \DateTimeImmutable('2026-07-29 05:00:00'));
        $intent = new BidIntent(BidAction::Up, 'cpa_below_target');
        $now = new \DateTimeImmutable('2026-07-29 12:00:00');

        $reason = (new CooldownGuard())->check($campaign, $cluster, $intent, 11000, $now);

        $this->assertNull($reason);
    }

    public function testAllowsFirstChangeWhenNoLastChange(): void
    {
        $campaign = new Campaign(1);
        $cluster = new Cluster($campaign, 1, 'q', 10000);
        $intent = new BidIntent(BidAction::Down, 'cpa_above_target');
        $now = new \DateTimeImmutable('2026-07-29 12:00:00');

        $reason = (new CooldownGuard())->check($campaign, $cluster, $intent, 8500, $now);

        $this->assertNull($reason);
    }
}

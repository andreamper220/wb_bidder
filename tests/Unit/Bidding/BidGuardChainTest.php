<?php

namespace App\Tests\Unit\Bidding;

use App\Bidding\Guard\BidGuardChain;
use App\Bidding\Guard\CooldownGuard;
use App\Bidding\Guard\MinMaxBidGuard;
use App\Bidding\ValueObject\BidIntent;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;
use PHPUnit\Framework\TestCase;

final class BidGuardChainTest extends TestCase
{
    public function testMinMaxGuardIsReachableWhenCalculatorDoesNotClamp(): void
    {
        $campaign = new Campaign(1);
        $campaign->setMinBidKopecks(5000);
        $campaign->setMaxBidKopecks(50000);
        $cluster = new Cluster($campaign, 1, 'q', 5500);
        $intent = new BidIntent(BidAction::Down, 'cpa_above_target');
        $now = new \DateTimeImmutable('2026-07-29 12:00:00');

        $chain = new BidGuardChain([new MinMaxBidGuard(), new CooldownGuard()]);
        $result = $chain->apply($campaign, $cluster, $intent, 4675, $now);

        $this->assertSame(BidAction::Hold, $result->action);
        $this->assertSame('below_min_bid', $result->reason);
    }
}

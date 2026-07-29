<?php

namespace App\Tests\Unit\Bidding;

use App\Bidding\Strategy\Level2\ClusterCpaStrategy;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;
use App\Metrics\ValueObject\ClusterMetrics;
use PHPUnit\Framework\TestCase;

final class ClusterCpaStrategyTest extends TestCase
{
    private ClusterCpaStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new ClusterCpaStrategy();
    }

    public function testCpaAboveTargetProposesDown(): void
    {
        $campaign = $this->campaign(targetCpa: '200');
        $cluster = new Cluster($campaign, 1, 'test');
        $metrics = new ClusterMetrics(1000, 100, 4, '1200.00', 7);

        $proposal = $this->strategy->propose($campaign, $cluster, $metrics);

        $this->assertSame(BidAction::Down, $proposal->action);
        $this->assertSame('cpa_above_target', $proposal->reason);
    }

    public function testCpaBelowTargetProposesUp(): void
    {
        $campaign = $this->campaign(targetCpa: '200');
        $cluster = new Cluster($campaign, 1, 'test');
        $metrics = new ClusterMetrics(1000, 100, 10, '400.00', 7);

        $proposal = $this->strategy->propose($campaign, $cluster, $metrics);

        $this->assertSame(BidAction::Up, $proposal->action);
        $this->assertSame('cpa_below_target', $proposal->reason);
    }

    public function testCpaWithinBufferHolds(): void
    {
        $campaign = $this->campaign(targetCpa: '200');
        $campaign->setCpaBuffer('20');
        $cluster = new Cluster($campaign, 1, 'test');
        // CPA = 190 — inside [180, 220]
        $metrics = new ClusterMetrics(1000, 100, 5, '950.00', 7);

        $proposal = $this->strategy->propose($campaign, $cluster, $metrics);

        $this->assertSame(BidAction::Hold, $proposal->action);
        $this->assertSame('cpa_within_buffer', $proposal->reason);
    }

    public function testOrdersBelowMinSkips(): void
    {
        $campaign = $this->campaign(targetCpa: '200', minOrders: 3);
        $cluster = new Cluster($campaign, 1, 'test');
        $metrics = new ClusterMetrics(1000, 100, 1, '100.00', 7);

        $proposal = $this->strategy->propose($campaign, $cluster, $metrics);

        $this->assertSame(BidAction::Hold, $proposal->action);
        $this->assertSame('orders_below_min', $proposal->reason);
    }

    private function campaign(string $targetCpa = '200', int $minOrders = 3): Campaign
    {
        $campaign = new Campaign(1, 'test');
        $campaign->setTargetCpa($targetCpa);
        $campaign->setCpaBuffer('20');
        $campaign->setMinOrders($minOrders);
        $campaign->setMinImpressions(100);

        return $campaign;
    }
}

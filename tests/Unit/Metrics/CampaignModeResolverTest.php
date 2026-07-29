<?php

namespace App\Tests\Unit\Metrics;

use App\Entity\Campaign;
use App\Enum\CampaignMode;
use App\Metrics\CampaignModeResolver;
use App\Metrics\ValueObject\CampaignMetrics;
use PHPUnit\Framework\TestCase;

final class CampaignModeResolverTest extends TestCase
{
    private CampaignModeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CampaignModeResolver();
    }

    public function testDefensiveWhenRoasLow(): void
    {
        $campaign = new Campaign(1);
        $campaign->setRestrictUpIfRoasBelow('3.0');
        $campaign->setAllowUpIfRoasAbove('5.0');

        $metrics = new CampaignMetrics(1000, 100, 10, '5000.00', '8000.00', 7);

        $this->assertSame(CampaignMode::Defensive, $this->resolver->resolve($campaign, $metrics));
    }

    public function testGrowthWhenRoasHigh(): void
    {
        $campaign = new Campaign(1);
        $campaign->setRestrictUpIfRoasBelow('3.0');
        $campaign->setAllowUpIfRoasAbove('5.0');

        $metrics = new CampaignMetrics(1000, 100, 10, '2000.00', '12000.00', 7);

        $this->assertSame(CampaignMode::Growth, $this->resolver->resolve($campaign, $metrics));
    }

    public function testBalancedInMiddle(): void
    {
        $campaign = new Campaign(1);
        $campaign->setRestrictUpIfRoasBelow('3.0');
        $campaign->setAllowUpIfRoasAbove('5.0');

        $metrics = new CampaignMetrics(1000, 100, 10, '2500.00', '10000.00', 7);

        $this->assertSame(CampaignMode::Balanced, $this->resolver->resolve($campaign, $metrics));
    }
}

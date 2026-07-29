<?php

namespace App\Tests\Unit\Bidding;

use App\Bidding\BidCalculator;
use App\Entity\Campaign;
use App\Enum\BidAction;
use App\Enum\CampaignMode;
use PHPUnit\Framework\TestCase;

final class BidCalculatorTest extends TestCase
{
    public function testGrowthUsesHigherMaxUp(): void
    {
        $campaign = new Campaign(1);
        $campaign->setMaxChangeUpPct(10);
        $campaign->setGrowthMaxChangeUpPct(15);
        $campaign->setMaxChangeDownPct(15);
        $campaign->setMinBidKopecks(1000);
        $campaign->setMaxBidKopecks(100000);

        $calculator = new BidCalculator();

        $balancedBid = $calculator->calculateNewBid($campaign, CampaignMode::Balanced, 10000, BidAction::Up);
        $growthBid = $calculator->calculateNewBid($campaign, CampaignMode::Growth, 10000, BidAction::Up);

        $this->assertSame(11000, $balancedBid);
        $this->assertSame(11500, $growthBid);
    }
}

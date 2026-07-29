<?php

namespace App\Tests\Unit\Bidding;

use App\Bidding\Merge\CampaignModeMerger;
use App\Bidding\ValueObject\BidProposal;
use App\Enum\BidAction;
use App\Enum\CampaignMode;
use PHPUnit\Framework\TestCase;

final class CampaignModeMergerTest extends TestCase
{
    private CampaignModeMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new CampaignModeMerger();
    }

    public function testDefensiveBlocksUp(): void
    {
        $intent = $this->merger->merge(CampaignMode::Defensive, new BidProposal(BidAction::Up, 'cpa_below_target'));

        $this->assertSame(BidAction::Hold, $intent->action);
        $this->assertSame('blocked_by_defensive_mode', $intent->reason);
        $this->assertSame(BidAction::Up, $intent->originalProposal);
    }

    public function testDefensiveAllowsDown(): void
    {
        $intent = $this->merger->merge(CampaignMode::Defensive, new BidProposal(BidAction::Down, 'cpa_above_target'));

        $this->assertSame(BidAction::Down, $intent->action);
    }

    public function testGrowthAllowsUp(): void
    {
        $intent = $this->merger->merge(CampaignMode::Growth, new BidProposal(BidAction::Up, 'cpa_below_target'));

        $this->assertSame(BidAction::Up, $intent->action);
    }
}

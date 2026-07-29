<?php

namespace App\Bidding;

use App\Entity\Campaign;
use App\Enum\BidAction;
use App\Enum\CampaignMode;

/**
 * Calculates the desired bid before guards. Does not clamp to min/max —
 * that is MinMaxBidGuard's job so boundary hits are visible in the decision log.
 */
final class BidCalculator
{
    public function calculateNewBid(Campaign $campaign, CampaignMode $mode, int $currentBidKopecks, BidAction $action): int
    {
        if ($action === BidAction::Hold || $action === BidAction::Pause) {
            return $currentBidKopecks;
        }

        $maxUpPct = $mode === CampaignMode::Growth
            ? $campaign->getGrowthMaxChangeUpPct()
            : $campaign->getMaxChangeUpPct();

        return match ($action) {
            BidAction::Up => intdiv($currentBidKopecks * (100 + $maxUpPct), 100),
            BidAction::Down => intdiv($currentBidKopecks * (100 - $campaign->getMaxChangeDownPct()), 100),
            default => $currentBidKopecks,
        };
    }
}

<?php

namespace App\Bidding;

use App\Entity\Campaign;
use App\Enum\BidAction;
use App\Enum\CampaignMode;

final class BidCalculator
{
    public function calculateNewBid(Campaign $campaign, CampaignMode $mode, int $currentBidKopecks, BidAction $action): int
    {
        if ($action === BidAction::Hold || $action === BidAction::Pause) {
            return $currentBidKopecks;
        }

        $maxUp = $mode === CampaignMode::Growth
            ? $campaign->getGrowthMaxChangeUpPct()
            : $campaign->getMaxChangeUpPct();

        $factor = match ($action) {
            BidAction::Up => 1 + ($maxUp / 100),
            BidAction::Down => 1 - ($campaign->getMaxChangeDownPct() / 100),
            default => 1.0,
        };

        $newBid = (int) round($currentBidKopecks * $factor);

        return max($campaign->getMinBidKopecks(), min($campaign->getMaxBidKopecks(), $newBid));
    }
}

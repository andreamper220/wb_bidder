<?php

namespace App\Bidding\Merge;

use App\Bidding\ValueObject\BidIntent;
use App\Bidding\ValueObject\BidProposal;
use App\Enum\BidAction;
use App\Enum\CampaignMode;

/**
 * Merges Level 2 proposal with Level 1 campaign mode (Pattern: merge/filter).
 */
final class CampaignModeMerger
{
    public function merge(CampaignMode $mode, BidProposal $proposal): BidIntent
    {
        if ($mode === CampaignMode::Defensive && $proposal->action === BidAction::Up) {
            return new BidIntent(
                BidAction::Hold,
                'blocked_by_defensive_mode',
                $proposal->action,
                'campaign_roas_low_restrict_up',
            );
        }

        return new BidIntent($proposal->action, $proposal->reason, $proposal->action, null);
    }
}

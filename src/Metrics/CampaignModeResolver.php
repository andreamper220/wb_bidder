<?php

namespace App\Metrics;

use App\Math\Bc;

use App\Entity\Campaign;
use App\Enum\CampaignMode;
use App\Metrics\ValueObject\CampaignMetrics;

/**
 * Level 1: resolves campaign mode from ROAS (Pattern: Strategy helper).
 */
final class CampaignModeResolver
{
    public function resolve(Campaign $campaign, CampaignMetrics $metrics): CampaignMode
    {
        $roas = $metrics->roas();

        if ($roas === null) {
            return CampaignMode::Balanced;
        }

        if (Bc::comp($roas, $campaign->getRestrictUpIfRoasBelow(), 4) < 0) {
            return CampaignMode::Defensive;
        }

        if (Bc::comp($roas, $campaign->getAllowUpIfRoasAbove(), 4) >= 0) {
            return CampaignMode::Growth;
        }

        return CampaignMode::Balanced;
    }
}

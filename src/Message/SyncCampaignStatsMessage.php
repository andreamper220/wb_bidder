<?php

namespace App\Message;

final readonly class SyncCampaignStatsMessage
{
    public function __construct(public int $campaignId)
    {
    }
}

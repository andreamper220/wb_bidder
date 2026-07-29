<?php

namespace App\Message;

final readonly class RunBiddingMessage
{
    public function __construct(public int $campaignId, public bool $dryRun = false)
    {
    }
}

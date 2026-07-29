<?php

namespace App\Message;

final readonly class ApplyBidDecisionMessage
{
    public function __construct(public int $bidDecisionId)
    {
    }
}

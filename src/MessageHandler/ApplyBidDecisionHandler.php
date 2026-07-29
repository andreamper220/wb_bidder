<?php

namespace App\MessageHandler;

use App\Message\ApplyBidDecisionMessage;
use App\Service\BidExecutionService;

#[AsMessageHandler(fromTransport: 'wb_execution')]
final class ApplyBidDecisionHandler
{
    public function __construct(private readonly BidExecutionService $executionService)
    {
    }

    public function __invoke(ApplyBidDecisionMessage $message): void
    {
        $this->executionService->apply($message->bidDecisionId);
    }
}

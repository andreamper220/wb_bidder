<?php

namespace App\MessageHandler;

use App\Message\ApplyBidDecisionMessage;
use App\Message\RunBiddingMessage;
use App\Repository\CampaignRepository;
use App\Sync\WbStatsSyncService;
use App\Message\SyncCampaignStatsMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(fromTransport: 'wb_sync')]
final class SyncCampaignStatsHandler
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly WbStatsSyncService $syncService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(SyncCampaignStatsMessage $message): void
    {
        $campaign = $this->campaignRepository->find($message->campaignId);
        if ($campaign === null) {
            return;
        }

        $this->syncService->syncCampaign($campaign);
        $this->messageBus->dispatch(new RunBiddingMessage($message->campaignId));
    }
}

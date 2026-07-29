<?php

namespace App\MessageHandler;

use App\Message\ApplyBidDecisionMessage;
use App\Message\RunBiddingMessage;
use App\Repository\BidDecisionRepository;
use App\Repository\CampaignRepository;
use App\Service\BidExecutionService;
use App\Bidding\Pipeline\BiddingPipeline;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(fromTransport: 'wb_bidding')]
final class RunBiddingHandler
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly BiddingPipeline $biddingPipeline,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(RunBiddingMessage $message): void
    {
        $campaign = $this->campaignRepository->find($message->campaignId);
        if ($campaign === null) {
            return;
        }

        $decisions = $this->biddingPipeline->run($campaign, $message->dryRun);

        if (!$message->dryRun && !$campaign->isDryRun()) {
            foreach ($decisions as $decision) {
                if ($decision->getId() !== null) {
                    $this->messageBus->dispatch(new ApplyBidDecisionMessage($decision->getId()));
                }
            }
        }
    }
}

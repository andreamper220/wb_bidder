<?php

namespace App\Service;

use App\Entity\BidDecision;
use App\Enum\BidAction;
use App\Enum\BidDecisionStatus;
use App\Repository\BidDecisionRepository;
use App\WbApi\WbPromotionApiClient;
use Doctrine\ORM\EntityManagerInterface;

final class BidExecutionService
{
    public function __construct(
        private readonly BidDecisionRepository $bidDecisionRepository,
        private readonly WbPromotionApiClient $wbApi,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function apply(int $bidDecisionId): void
    {
        $decision = $this->bidDecisionRepository->find($bidDecisionId);
        if ($decision === null || $decision->getStatus() !== BidDecisionStatus::Pending) {
            return;
        }

        $cluster = $decision->getCluster();
        $campaign = $decision->getCampaign();

        try {
            if ($decision->getFinalAction() === BidAction::Pause) {
                $cluster->setPaused(true);
            } elseif ($decision->getFinalAction() !== BidAction::Hold
                && $decision->getNewBidKopecks() !== $decision->getOldBidKopecks()) {
                $this->wbApi->setClusterBid(
                    $campaign->getWbAdvertId(),
                    $cluster->getNmId(),
                    $cluster->getNormQuery(),
                    $decision->getNewBidKopecks(),
                );
                $cluster->setCurrentBidKopecks($decision->getNewBidKopecks());
                $cluster->setLastBidChangeAt(new \DateTimeImmutable());
            }

            $decision->setStatus(BidDecisionStatus::Applied);
            $decision->setAppliedAt(new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $decision->setStatus(BidDecisionStatus::Failed);
            $decision->setReason($decision->getReason() . '; error=' . $e->getMessage());
        }

        $this->entityManager->flush();
    }
}

<?php

namespace App\Service;

use App\Entity\BidDecision;
use App\Entity\BidSnapshot;
use App\Entity\Campaign;
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
            $this->ensureSnapshot($campaign);

            if ($decision->getFinalAction() === BidAction::Pause) {
                $this->wbApi->deleteClusterBids([[
                    'advertId' => $campaign->getWbAdvertId(),
                    'nmId' => $cluster->getNmId(),
                    'normQuery' => $cluster->getNormQuery(),
                ]]);
                $cluster->setPaused(true);
            } else {
                $resumeFromPause = $cluster->isPaused();
                $bidChanged = $decision->getFinalAction() !== BidAction::Hold
                    && $decision->getNewBidKopecks() !== $decision->getOldBidKopecks();

                if ($resumeFromPause || $bidChanged) {
                    $this->wbApi->setClusterBids([[
                        'advertId' => $campaign->getWbAdvertId(),
                        'nmId' => $cluster->getNmId(),
                        'normQuery' => $cluster->getNormQuery(),
                        'bidKopecks' => $decision->getNewBidKopecks(),
                    ]]);
                    $cluster->setCurrentBidKopecks($decision->getNewBidKopecks());
                    $cluster->setPaused(false);
                    if ($bidChanged) {
                        $cluster->setLastBidChangeAt(new \DateTimeImmutable());
                    }
                }
            }

            $decision->setStatus(BidDecisionStatus::Applied);
            $decision->setAppliedAt(new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $decision->setStatus(BidDecisionStatus::Failed);
            $decision->setReason($decision->getReason() . '; error=' . $e->getMessage());
        }

        $this->entityManager->flush();
    }

    private function ensureSnapshot(Campaign $campaign): void
    {
        $existing = $this->entityManager->getRepository(BidSnapshot::class)->findOneBy(
            ['campaign' => $campaign],
            ['createdAt' => 'DESC'],
        );

        // One snapshot per calendar day is enough for rollback of a bad run.
        if ($existing !== null && $existing->getCreatedAt()->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d')) {
            return;
        }

        $payload = [];
        foreach ($campaign->getClusters() as $cluster) {
            if ($cluster->getId() === null) {
                continue;
            }
            $payload[(string) $cluster->getId()] = [
                'normQuery' => $cluster->getNormQuery(),
                'bidKopecks' => $cluster->getCurrentBidKopecks(),
                'paused' => $cluster->isPaused(),
            ];
        }

        $snapshot = new BidSnapshot($campaign, $payload);
        $this->entityManager->persist($snapshot);
    }
}

<?php

namespace App\Service;

use App\Entity\BidSnapshot;
use App\Entity\Campaign;
use App\Repository\BidSnapshotRepository;
use App\WbApi\WbPromotionApiClient;
use Doctrine\ORM\EntityManagerInterface;

final class BidSnapshotRestoreService
{
    public function __construct(
        private readonly BidSnapshotRepository $bidSnapshotRepository,
        private readonly WbPromotionApiClient $wbApi,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Restores cluster bids from the latest snapshot.
     *
     * When $dryRun is true: returns the snapshot without mutating DB or calling WB.
     */
    public function restoreLatest(Campaign $campaign, bool $dryRun = true): ?BidSnapshot
    {
        $snapshot = $this->bidSnapshotRepository->findOneBy(
            ['campaign' => $campaign],
            ['createdAt' => 'DESC'],
        );
        if ($snapshot === null) {
            return null;
        }

        if ($dryRun) {
            return $snapshot;
        }

        $bidsToSet = [];
        $bidsToDelete = [];
        foreach ($campaign->getClusters() as $cluster) {
            $id = (string) $cluster->getId();
            if (!isset($snapshot->getPayload()[$id])) {
                continue;
            }
            $row = $snapshot->getPayload()[$id];
            $cluster->setCurrentBidKopecks($row['bidKopecks']);
            $cluster->setPaused($row['paused']);
            if ($row['paused']) {
                $bidsToDelete[] = [
                    'advertId' => $campaign->getWbAdvertId(),
                    'nmId' => $cluster->getNmId(),
                    'normQuery' => $cluster->getNormQuery(),
                ];
            } else {
                $bidsToSet[] = [
                    'advertId' => $campaign->getWbAdvertId(),
                    'nmId' => $cluster->getNmId(),
                    'normQuery' => $cluster->getNormQuery(),
                    'bidKopecks' => $row['bidKopecks'],
                ];
            }
        }

        foreach (array_chunk($bidsToSet, 100) as $chunk) {
            $this->wbApi->setClusterBids($chunk);
        }
        foreach (array_chunk($bidsToDelete, 100) as $chunk) {
            $this->wbApi->deleteClusterBids($chunk);
        }

        $this->entityManager->flush();

        return $snapshot;
    }
}

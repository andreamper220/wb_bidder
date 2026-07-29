<?php

namespace App\Service;

use App\Entity\BidDecision;
use App\Entity\BidSnapshot;
use App\Entity\Campaign;
use App\Entity\CampaignDailyStat;
use App\Entity\ClusterDailyStat;
use Doctrine\ORM\EntityManagerInterface;

final class CampaignRemovalService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function delete(Campaign $campaign): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(BidDecision::class, 'd')
            ->where('d.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->getQuery()
            ->execute();

        $this->entityManager->createQueryBuilder()
            ->delete(BidSnapshot::class, 's')
            ->where('s.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->getQuery()
            ->execute();

        foreach ($campaign->getClusters() as $cluster) {
            $this->entityManager->createQueryBuilder()
                ->delete(ClusterDailyStat::class, 's')
                ->where('s.cluster = :cluster')
                ->setParameter('cluster', $cluster)
                ->getQuery()
                ->execute();
        }

        $this->entityManager->createQueryBuilder()
            ->delete(CampaignDailyStat::class, 's')
            ->where('s.campaign = :campaign')
            ->setParameter('campaign', $campaign)
            ->getQuery()
            ->execute();

        $this->entityManager->remove($campaign);
        $this->entityManager->flush();
    }
}

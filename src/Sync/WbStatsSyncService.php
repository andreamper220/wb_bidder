<?php

namespace App\Sync;

use App\Entity\Campaign;
use App\Entity\CampaignDailyStat;
use App\Entity\Cluster;
use App\Entity\ClusterDailyStat;
use App\Repository\CampaignRepository;
use App\WbApi\Dto\NormqueryClusterStatDto;
use App\WbApi\WbPromotionApiClient;
use Doctrine\ORM\EntityManagerInterface;

final class WbStatsSyncService
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly WbPromotionApiClient $wbApi,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function syncCampaign(Campaign $campaign): void
    {
        $to = new \DateTimeImmutable('today');
        $from = $to->modify(sprintf('-%d days', $campaign->getMetricsWindowDays()));

        $fullstats = $this->wbApi->getFullstats($campaign->getWbAdvertId(), $from, $to);

        foreach ($fullstats as $dto) {
            foreach ($dto->days as $day) {
                $stat = $this->findOrCreateCampaignStat($campaign, $day->date);
                $stat->setViews($day->views);
                $stat->setClicks($day->clicks);
                $stat->setOrders($day->orders);
                $stat->setSpend($day->spend);
                $stat->setRevenue($day->revenue);
            }
        }

        $nmId = $this->resolveNmId($campaign);
        if ($nmId === null) {
            $this->entityManager->flush();

            return;
        }

        $normStats = $this->wbApi->getNormqueryStats($campaign->getWbAdvertId(), $nmId, $from, $to);

        foreach ($normStats as $dto) {
            $cluster = $this->findOrCreateCluster($campaign, $dto);
            $stat = $this->findOrCreateClusterStat($cluster, $dto->date);
            $stat->setViews($dto->views);
            $stat->setClicks($dto->clicks);
            $stat->setOrders($dto->orders);
            $stat->setSpend($dto->spend);
        }

        $this->syncClusterBids($campaign);

        $this->entityManager->flush();
    }

    public function syncAll(): int
    {
        $count = 0;
        foreach ($this->campaignRepository->findAll() as $campaign) {
            $this->syncCampaign($campaign);
            ++$count;
        }

        return $count;
    }

    private function resolveNmId(Campaign $campaign): ?int
    {
        $cluster = $campaign->getClusters()->first();

        return $cluster ? $cluster->getNmId() : 987654321;
    }

    private function findOrCreateCluster(Campaign $campaign, NormqueryClusterStatDto $dto): Cluster
    {
        foreach ($campaign->getClusters() as $cluster) {
            if ($cluster->getNormQuery() === $dto->normQuery) {
                return $cluster;
            }
        }

        $cluster = new Cluster($campaign, $dto->nmId, $dto->normQuery);
        $campaign->addCluster($cluster);
        $this->entityManager->persist($cluster);

        return $cluster;
    }

    private function syncClusterBids(Campaign $campaign): void
    {
        $nmIds = [];
        foreach ($campaign->getClusters() as $cluster) {
            $nmIds[$cluster->getNmId()] = true;
        }

        if ($nmIds === []) {
            return;
        }

        foreach (array_keys($nmIds) as $nmId) {
            $bids = $this->wbApi->getNormqueryBids($campaign->getWbAdvertId(), $nmId);

            foreach ($bids as $bidDto) {
                $cluster = $this->findClusterByNormQuery($campaign, $bidDto->normQuery);
                if ($cluster !== null) {
                    $cluster->setCurrentBidKopecks($bidDto->bidKopecks);
                }
            }
        }
    }

    private function findClusterByNormQuery(Campaign $campaign, string $normQuery): ?Cluster
    {
        foreach ($campaign->getClusters() as $cluster) {
            if ($cluster->getNormQuery() === $normQuery) {
                return $cluster;
            }
        }

        return null;
    }

    private function findOrCreateCampaignStat(Campaign $campaign, \DateTimeImmutable $date): CampaignDailyStat
    {
        $existing = $this->entityManager->getRepository(CampaignDailyStat::class)->findOneBy([
            'campaign' => $campaign,
            'date' => $date,
        ]);

        if ($existing !== null) {
            return $existing;
        }

        $stat = new CampaignDailyStat($campaign, $date);
        $this->entityManager->persist($stat);

        return $stat;
    }

    private function findOrCreateClusterStat(Cluster $cluster, \DateTimeImmutable $date): ClusterDailyStat
    {
        $existing = $this->entityManager->getRepository(ClusterDailyStat::class)->findOneBy([
            'cluster' => $cluster,
            'date' => $date,
        ]);

        if ($existing !== null) {
            return $existing;
        }

        $stat = new ClusterDailyStat($cluster, $date);
        $this->entityManager->persist($stat);

        return $stat;
    }
}

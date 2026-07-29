<?php

namespace App\Sync;

use App\Entity\Campaign;
use App\Entity\CampaignDailyStat;
use App\Entity\Cluster;
use App\Entity\ClusterDailyStat;
use App\Repository\CampaignDailyStatRepository;
use App\Repository\CampaignRepository;
use App\Repository\ClusterDailyStatRepository;
use App\WbApi\Dto\FullstatsCampaignDto;
use App\WbApi\Dto\NormqueryClusterBidDto;
use App\WbApi\Dto\NormqueryClusterStatDto;
use App\WbApi\WbPromotionApiClient;
use Doctrine\ORM\EntityManagerInterface;

final class WbStatsSyncService
{
    /** WB fullstats works for campaign statuses 7, 9, 11. */
    private const SYNCABLE_STATUSES = [7, 9, 11];

    private const FULLSTATS_BATCH = 50;

    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly CampaignDailyStatRepository $campaignDailyStatRepository,
        private readonly ClusterDailyStatRepository $clusterDailyStatRepository,
        private readonly WbPromotionApiClient $wbApi,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function syncCampaign(Campaign $campaign): void
    {
        if (!$this->isSyncable($campaign)) {
            return;
        }

        [$from, $to] = $this->windowFor($campaign);
        $fullstats = $this->wbApi->getFullstats([$campaign->getWbAdvertId()], $from, $to);
        $this->applyFullstats($campaign, $fullstats);

        $items = $this->itemsForCampaign($campaign);
        if ($items === []) {
            $this->entityManager->flush();

            return;
        }

        $normStats = $this->wbApi->getNormqueryStats($items, $from, $to);
        $this->applyNormqueryStats($campaign, $normStats);
        $this->applyBids($campaign, $this->wbApi->getNormqueryBids($items));

        $this->entityManager->flush();
    }

    public function syncAll(): int
    {
        $campaigns = [];
        foreach ($this->campaignRepository->findActive() as $campaign) {
            if ($this->isSyncable($campaign)) {
                $campaigns[] = $campaign;
            }
        }

        if ($campaigns === []) {
            return 0;
        }

        // Longest window among campaigns so each gets enough history in one batch.
        $maxSpan = 0;
        foreach ($campaigns as $campaign) {
            $maxSpan = max($maxSpan, $campaign->getMetricsWindowDays() + $campaign->getAttributionLagDays());
        }
        $to = new \DateTimeImmutable('today');
        $from = $to->modify(sprintf('-%d days', $maxSpan));

        $byAdvertId = [];
        foreach ($campaigns as $campaign) {
            $byAdvertId[$campaign->getWbAdvertId()] = $campaign;
        }

        foreach (array_chunk(array_keys($byAdvertId), self::FULLSTATS_BATCH) as $chunk) {
            $fullstats = $this->wbApi->getFullstats($chunk, $from, $to);
            foreach ($fullstats as $dto) {
                $campaign = $byAdvertId[$dto->advertId] ?? null;
                if ($campaign !== null) {
                    $this->applyFullstats($campaign, [$dto]);
                }
            }
        }

        $allItems = [];
        $campaignByAdvert = $byAdvertId;
        foreach ($campaigns as $campaign) {
            foreach ($this->itemsForCampaign($campaign) as $item) {
                $allItems[] = $item;
            }
        }

        if ($allItems !== []) {
            // Unique by advertId+nmId
            $unique = [];
            foreach ($allItems as $item) {
                $unique[$item['advertId'] . ':' . $item['nmId']] = $item;
            }
            $allItems = array_values($unique);

            $normStats = $this->wbApi->getNormqueryStats($allItems, $from, $to);
            $statsByAdvert = [];
            foreach ($normStats as $dto) {
                $statsByAdvert[$dto->advertId][] = $dto;
            }
            foreach ($statsByAdvert as $advertId => $stats) {
                $campaign = $campaignByAdvert[$advertId] ?? null;
                if ($campaign !== null) {
                    $this->applyNormqueryStats($campaign, $stats);
                }
            }

            $bids = $this->wbApi->getNormqueryBids($allItems);
            $bidsByAdvert = [];
            foreach ($bids as $bid) {
                $bidsByAdvert[$bid->advertId][] = $bid;
            }
            foreach ($bidsByAdvert as $advertId => $campaignBids) {
                $campaign = $campaignByAdvert[$advertId] ?? null;
                if ($campaign !== null) {
                    $this->applyBids($campaign, $campaignBids);
                }
            }
        }

        $this->entityManager->flush();

        return \count($campaigns);
    }

    private function isSyncable(Campaign $campaign): bool
    {
        if (!$campaign->isActive()) {
            return false;
        }

        $status = $campaign->getWbStatus();
        if ($status === null) {
            return true;
        }

        return \in_array($status, self::SYNCABLE_STATUSES, true);
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function windowFor(Campaign $campaign): array
    {
        $to = new \DateTimeImmutable('today');
        $from = $to->modify(sprintf('-%d days', $campaign->getMetricsWindowDays() + $campaign->getAttributionLagDays()));

        return [$from, $to];
    }

    /**
     * @param list<FullstatsCampaignDto> $fullstats
     */
    private function applyFullstats(Campaign $campaign, array $fullstats): void
    {
        $campaignDates = [];
        foreach ($fullstats as $dto) {
            foreach ($dto->days as $day) {
                $campaignDates[$day->date->format('Y-m-d')] = $day;
            }
        }
        $existingCampaignStats = $this->campaignDailyStatRepository->findIndexedByDate(
            $campaign,
            array_keys($campaignDates),
        );
        foreach ($campaignDates as $dateKey => $day) {
            $stat = $existingCampaignStats[$dateKey] ?? new CampaignDailyStat($campaign, $day->date);
            if (!isset($existingCampaignStats[$dateKey])) {
                $this->entityManager->persist($stat);
            }
            $stat->setViews($day->views);
            $stat->setClicks($day->clicks);
            $stat->setOrders($day->orders);
            $stat->setSpend($day->spend);
            $stat->setRevenue($day->revenue);
        }
    }

    /**
     * @return list<array{advertId: int, nmId: int}>
     */
    private function itemsForCampaign(Campaign $campaign): array
    {
        $nmIds = [];
        foreach ($campaign->getClusters() as $cluster) {
            $nmIds[$cluster->getNmId()] = true;
        }

        if ($campaign->getSeedNmId() !== null) {
            $nmIds[$campaign->getSeedNmId()] = true;
        }

        $items = [];
        foreach (array_keys($nmIds) as $nmId) {
            $items[] = ['advertId' => $campaign->getWbAdvertId(), 'nmId' => (int) $nmId];
        }

        return $items;
    }

    /**
     * @param list<NormqueryClusterStatDto> $normStats
     */
    private function applyNormqueryStats(Campaign $campaign, array $normStats): void
    {
        $statsByClusterDate = [];
        foreach ($normStats as $dto) {
            $cluster = $this->findOrCreateCluster($campaign, $dto);
            $statsByClusterDate[$cluster->getNormQuery()][$dto->date->format('Y-m-d')] = $dto;
        }

        // Persist new clusters so findIndexedByDate can bind them.
        $this->entityManager->flush();

        foreach ($campaign->getClusters() as $cluster) {
            $byDate = $statsByClusterDate[$cluster->getNormQuery()] ?? [];
            if ($byDate === []) {
                continue;
            }
            $existing = $this->clusterDailyStatRepository->findIndexedByDate($cluster, array_keys($byDate));
            foreach ($byDate as $dateKey => $dto) {
                $stat = $existing[$dateKey] ?? new ClusterDailyStat($cluster, $dto->date);
                if (!isset($existing[$dateKey])) {
                    $this->entityManager->persist($stat);
                }
                $stat->setViews($dto->views);
                $stat->setClicks($dto->clicks);
                $stat->setOrders($dto->orders);
                $stat->setSpend($dto->spend);
            }
        }
    }

    /**
     * @param list<NormqueryClusterBidDto> $bids
     */
    private function applyBids(Campaign $campaign, array $bids): void
    {
        foreach ($bids as $bidDto) {
            $cluster = $this->findClusterByNormQuery($campaign, $bidDto->normQuery);
            if ($cluster !== null) {
                $cluster->setCurrentBidKopecks($bidDto->bidKopecks);
            }
        }
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

    private function findClusterByNormQuery(Campaign $campaign, string $normQuery): ?Cluster
    {
        foreach ($campaign->getClusters() as $cluster) {
            if ($cluster->getNormQuery() === $normQuery) {
                return $cluster;
            }
        }

        return null;
    }
}

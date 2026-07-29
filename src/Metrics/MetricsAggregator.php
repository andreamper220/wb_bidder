<?php

namespace App\Metrics;

use App\Math\Bc;

use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Metrics\ValueObject\CampaignMetrics;
use App\Metrics\ValueObject\ClusterMetrics;
use App\Repository\CampaignDailyStatRepository;
use App\Repository\ClusterDailyStatRepository;

/**
 * Aggregates daily stats into window metrics for bidding.
 */
final class MetricsAggregator
{
    public function __construct(
        private readonly CampaignDailyStatRepository $campaignDailyStatRepository,
        private readonly ClusterDailyStatRepository $clusterDailyStatRepository,
    ) {
    }

    public function aggregateCampaign(Campaign $campaign): CampaignMetrics
    {
        $stats = $this->campaignDailyStatRepository->findForWindow(
            $campaign,
            $campaign->getMetricsWindowDays(),
            $campaign->getAttributionLagDays(),
        );

        $views = 0;
        $clicks = 0;
        $orders = 0;
        $spend = '0';
        $revenue = '0';

        foreach ($stats as $stat) {
            $views += $stat->getViews();
            $clicks += $stat->getClicks();
            $orders += $stat->getOrders();
            $spend = Bc::add($spend, $stat->getSpend(), 2);
            $revenue = Bc::add($revenue, $stat->getRevenue(), 2);
        }

        return new CampaignMetrics($views, $clicks, $orders, $spend, $revenue, $campaign->getMetricsWindowDays());
    }

    public function aggregateCluster(Cluster $cluster, int $windowDays): ClusterMetrics
    {
        $stats = $this->clusterDailyStatRepository->findForWindow(
            $cluster,
            $windowDays,
            $cluster->getCampaign()->getAttributionLagDays(),
        );

        $views = 0;
        $clicks = 0;
        $orders = 0;
        $spend = '0';

        foreach ($stats as $stat) {
            $views += $stat->getViews();
            $clicks += $stat->getClicks();
            $orders += $stat->getOrders();
            $spend = Bc::add($spend, $stat->getSpend(), 2);
        }

        return new ClusterMetrics($views, $clicks, $orders, $spend, $windowDays);
    }
}

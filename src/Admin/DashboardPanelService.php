<?php

namespace App\Admin;

use App\Entity\BidDecision;
use App\Entity\Campaign;
use App\Enum\BidDecisionStatus;
use App\Enum\CampaignMode;
use App\Metrics\CampaignModeResolver;
use App\Metrics\MetricsAggregator;
use App\Repository\BidDecisionRepository;
use App\Repository\CampaignRepository;

final class DashboardPanelService
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        private readonly BidDecisionRepository $bidDecisionRepository,
        private readonly MetricsAggregator $metricsAggregator,
        private readonly CampaignModeResolver $campaignModeResolver,
    ) {
    }

    public function build(): array
    {
        $campaigns = $this->campaignRepository->findAll();
        $campaignRows = [];
        $biddingEnabled = 0;
        $modeCounts = [
            CampaignMode::Defensive->value => 0,
            CampaignMode::Balanced->value => 0,
            CampaignMode::Growth->value => 0,
        ];

        foreach ($campaigns as $campaign) {
            if ($campaign->isBiddingEnabled()) {
                ++$biddingEnabled;
            }

            $metrics = $this->metricsAggregator->aggregateCampaign($campaign);
            $mode = $campaign->isLevel1Enabled()
                ? $this->campaignModeResolver->resolve($campaign, $metrics)
                : CampaignMode::Balanced;

            $modeCounts[$mode->value] = ($modeCounts[$mode->value] ?? 0) + 1;

            $campaignRows[] = [
                'id' => $campaign->getId(),
                'name' => $campaign->getName(),
                'wbAdvertId' => $campaign->getWbAdvertId(),
                'biddingEnabled' => $campaign->isBiddingEnabled(),
                'dryRun' => $campaign->isDryRun(),
                'mode' => $mode,
                'roas' => $metrics->roas(),
                'spend' => $metrics->spend,
                'revenue' => $metrics->revenue,
                'orders' => $metrics->orders,
                'clusters' => $campaign->getClusters()->count(),
            ];
        }

        $recentDecisions = $this->bidDecisionRepository->findRecent(15);
        $statusCounts = $this->bidDecisionRepository->countByStatus();

        return [
            'summary' => [
                'campaignsTotal' => count($campaigns),
                'biddingEnabled' => $biddingEnabled,
                'decisionsTotal' => $statusCounts['total'],
                'decisionsPending' => $statusCounts[BidDecisionStatus::Pending->value] ?? 0,
                'decisionsApplied' => $statusCounts[BidDecisionStatus::Applied->value] ?? 0,
                'decisionsSkipped' => $statusCounts[BidDecisionStatus::Skipped->value] ?? 0,
                'modeDefensive' => $modeCounts[CampaignMode::Defensive->value],
                'modeBalanced' => $modeCounts[CampaignMode::Balanced->value],
                'modeGrowth' => $modeCounts[CampaignMode::Growth->value],
                'mockApi' => (bool) ($_ENV['WB_API_MOCK'] ?? true),
            ],
            'campaigns' => $campaignRows,
            'recentDecisions' => $recentDecisions,
        ];
    }
}

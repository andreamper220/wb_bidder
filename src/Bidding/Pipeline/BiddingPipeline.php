<?php

namespace App\Bidding\Pipeline;

use App\Bidding\BidCalculator;
use App\Bidding\Guard\BidGuardChain;
use App\Bidding\Merge\CampaignModeMerger;
use App\Bidding\Strategy\Level2\ClusterCpaStrategy;
use App\Entity\BidDecision;
use App\Entity\Campaign;
use App\Enum\BidAction;
use App\Enum\BidDecisionStatus;
use App\Enum\CampaignMode;
use App\Metrics\CampaignModeResolver;
use App\Metrics\MetricsAggregator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Pattern: Pipeline — aggregate → mode → propose → merge → guard → decision.
 */
final class BiddingPipeline
{
    public function __construct(
        private readonly MetricsAggregator $metricsAggregator,
        private readonly CampaignModeResolver $campaignModeResolver,
        private readonly ClusterCpaStrategy $clusterCpaStrategy,
        private readonly CampaignModeMerger $campaignModeMerger,
        private readonly BidCalculator $bidCalculator,
        private readonly BidGuardChain $bidGuardChain,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return BidDecision[] */
    public function run(Campaign $campaign, bool $dryRun = false): array
    {
        if (!$campaign->isBiddingEnabled() || !$campaign->isActive()) {
            return [];
        }

        $campaignMetrics = $this->metricsAggregator->aggregateCampaign($campaign);
        $mode = $campaign->isLevel1Enabled()
            ? $this->campaignModeResolver->resolve($campaign, $campaignMetrics)
            : CampaignMode::Balanced;

        $decisions = [];

        foreach ($campaign->getClusters() as $cluster) {
            if (!$campaign->isLevel2Enabled()) {
                continue;
            }

            $clusterMetrics = $this->metricsAggregator->aggregateCluster(
                $cluster,
                $campaign->getMetricsWindowDays(),
            );

            $proposal = $this->clusterCpaStrategy->propose($campaign, $cluster, $clusterMetrics);
            $intent = $this->campaignModeMerger->merge($mode, $proposal);

            $newBid = $this->bidCalculator->calculateNewBid(
                $campaign,
                $mode,
                $cluster->getCurrentBidKopecks(),
                $intent->action,
            );

            $guardedIntent = $this->bidGuardChain->apply($campaign, $cluster, $intent, $newBid);
            if ($guardedIntent->action === BidAction::Hold) {
                $newBid = $cluster->getCurrentBidKopecks();
            }

            if ($intent->action === BidAction::Pause) {
                $cluster->setPaused(true);
            }

            $reason = $this->buildReason($mode, $proposal, $guardedIntent, $campaignMetrics, $clusterMetrics);

            $decision = new BidDecision(
                $campaign,
                $cluster,
                $guardedIntent->action,
                $cluster->getCurrentBidKopecks(),
                $newBid,
                $reason,
            );
            $decision->setCampaignMode($mode);
            $decision->setProposalAction($proposal->action);

            if ($guardedIntent->action === BidAction::Hold && $proposal->action !== BidAction::Hold) {
                $decision->setStatus(BidDecisionStatus::Skipped);
            } elseif ($dryRun || $campaign->isDryRun()) {
                $decision->setStatus(BidDecisionStatus::Skipped);
            } else {
                $decision->setStatus(BidDecisionStatus::Pending);
            }

            $this->entityManager->persist($decision);
            $decisions[] = $decision;
        }

        $this->entityManager->flush();

        return $decisions;
    }

    private function buildReason(
        CampaignMode $mode,
        \App\Bidding\ValueObject\BidProposal $proposal,
        \App\Bidding\ValueObject\BidIntent $intent,
        \App\Metrics\ValueObject\CampaignMetrics $campaignMetrics,
        \App\Metrics\ValueObject\ClusterMetrics $clusterMetrics,
    ): string {
        $parts = [
            'mode=' . $mode->value,
            'proposal=' . $proposal->action->value,
            'final=' . $intent->action->value,
            'campaign_roas=' . ($campaignMetrics->roas() ?? 'n/a'),
            'cluster_cpa=' . ($clusterMetrics->cpa() ?? 'n/a'),
            'reason=' . $intent->reason,
        ];

        if ($intent->modeFilterReason !== null) {
            $parts[] = 'mode_filter=' . $intent->modeFilterReason;
        }

        return implode('; ', $parts);
    }
}

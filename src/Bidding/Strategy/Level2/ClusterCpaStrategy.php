<?php

namespace App\Bidding\Strategy\Level2;

use App\Bidding\ValueObject\BidProposal;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;
use App\Math\Bc;
use App\Metrics\ValueObject\ClusterMetrics;

/**
 * Level 2 — CPA per search cluster (Pattern: Strategy).
 */
final class ClusterCpaStrategy
{
    public function propose(Campaign $campaign, Cluster $cluster, ClusterMetrics $metrics): BidProposal
    {
        if ($metrics->impressions < $campaign->getMinImpressions()) {
            return new BidProposal(BidAction::Hold, 'impressions_below_min');
        }

        if ($metrics->orders < $campaign->getMinOrders()) {
            return new BidProposal(BidAction::Hold, 'orders_below_min');
        }

        $cpa = $metrics->cpa();
        if ($cpa === null) {
            return new BidProposal(BidAction::Hold, 'cpa_not_available');
        }

        $target = $campaign->getTargetCpa();
        $buffer = $campaign->getCpaBuffer();

        if ($campaign->getPauseIfCpaAbove() !== null
            && Bc::comp($cpa, $campaign->getPauseIfCpaAbove(), 4) > 0
            && Bc::comp($metrics->spend, $target, 2) > 0
        ) {
            return new BidProposal(BidAction::Pause, 'cpa_above_pause_threshold');
        }

        if (Bc::comp($cpa, $target, 4) === 0 || Bc::comp(Bc::sub($cpa, $target, 4), '0', 4) === 0) {
            return new BidProposal(BidAction::Hold, 'cpa_equals_target');
        }

        if (Bc::comp($cpa, Bc::add($target, $buffer, 4), 4) > 0) {
            return new BidProposal(BidAction::Down, 'cpa_above_target');
        }

        if (Bc::comp($cpa, $target, 4) < 0) {
            return new BidProposal(BidAction::Up, 'cpa_below_target');
        }

        return new BidProposal(BidAction::Hold, 'cpa_within_buffer');
    }
}

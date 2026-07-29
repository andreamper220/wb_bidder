<?php

namespace App\Demo;

use App\Bidding\Pipeline\BiddingPipeline;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Metrics\CampaignModeResolver;
use App\Metrics\MetricsAggregator;
use App\Repository\CampaignRepository;
use App\Sync\WbStatsSyncService;
use Doctrine\ORM\EntityManagerInterface;

final class DemoStandService
{
    public const DEMO_WB_ADVERT_ID = 100001;
    public const DEFAULT_RESTRICT_UP_IF_ROAS_BELOW = '3.0';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CampaignRepository $campaignRepository,
        private readonly WbStatsSyncService $statsSyncService,
        private readonly BiddingPipeline $biddingPipeline,
        private readonly MetricsAggregator $metricsAggregator,
        private readonly CampaignModeResolver $campaignModeResolver,
    ) {
    }

    public function run(bool $reset = false, ?string $restrictUpIfRoasBelow = null): DemoStandResult
    {
        $restrictUpIfRoasBelow = $this->normalizeRestrictThreshold($restrictUpIfRoasBelow);

        $existing = $this->campaignRepository->findOneBy(['wbAdvertId' => self::DEMO_WB_ADVERT_ID]);
        $created = false;
        $wasReset = false;

        if ($existing !== null && $reset) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
            $existing = null;
            $wasReset = true;
        }

        if ($existing === null) {
            $campaign = $this->createDemoCampaign($restrictUpIfRoasBelow);
            $this->entityManager->persist($campaign);
            $this->entityManager->flush();
            $created = true;
        } else {
            $campaign = $existing;
            $campaign->setRestrictUpIfRoasBelow($restrictUpIfRoasBelow);
            $this->entityManager->flush();
        }

        $this->statsSyncService->syncCampaign($campaign);
        $decisions = $this->biddingPipeline->run($campaign, dryRun: true);

        $metrics = $this->metricsAggregator->aggregateCampaign($campaign);
        $mode = $campaign->isLevel1Enabled()
            ? $this->campaignModeResolver->resolve($campaign, $metrics)
            : \App\Enum\CampaignMode::Balanced;

        return new DemoStandResult(
            campaignId: $campaign->getId() ?? 0,
            campaignName: $campaign->getName(),
            decisionsCount: count($decisions),
            created: $created,
            reset: $wasReset,
            roas: $metrics->roas(),
            campaignMode: $mode,
            restrictUpIfRoasBelow: $restrictUpIfRoasBelow,
        );
    }

    private function normalizeRestrictThreshold(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return self::DEFAULT_RESTRICT_UP_IF_ROAS_BELOW;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('restrict_up_if_roas_below должен быть числом.');
        }

        $float = (float) $value;
        if ($float <= 0) {
            throw new \InvalidArgumentException('restrict_up_if_roas_below должен быть больше 0.');
        }

        return number_format($float, 4, '.', '');
    }

    private function createDemoCampaign(string $restrictUpIfRoasBelow): Campaign
    {
        $campaign = new Campaign(self::DEMO_WB_ADVERT_ID, 'Demo кроссовки');
        $campaign->setBiddingEnabled(true);
        $campaign->setDryRun(true);
        $campaign->setRestrictUpIfRoasBelow($restrictUpIfRoasBelow);
        $campaign->setAllowUpIfRoasAbove('5.0');
        $campaign->setTargetCpa('500.00');
        $campaign->setMaxBidKopecks(50000);

        $campaign->addCluster(new Cluster($campaign, 987654321, 'кроссовки мужские', 10000));
        $campaign->addCluster(new Cluster($campaign, 987654321, 'кроссовки черные', 10000));
        $campaign->addCluster(new Cluster($campaign, 987654321, 'кроссовки бег', 10000));

        return $campaign;
    }
}

<?php

namespace App\Tests\Unit\Sync;

use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Repository\CampaignDailyStatRepository;
use App\Repository\CampaignRepository;
use App\Repository\ClusterDailyStatRepository;
use App\Sync\WbStatsSyncService;
use App\Tests\Support\StubWbPromotionApiClient;
use App\WbApi\Dto\FullstatsCampaignDto;
use App\WbApi\Dto\FullstatsDayDto;
use App\WbApi\Dto\NormqueryClusterBidDto;
use App\WbApi\Dto\NormqueryClusterStatDto;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class WbStatsSyncServiceBidsTest extends TestCase
{
    public function testSyncCampaignAppliesBidsFromGetBidsEndpoint(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');
        $campaign = new Campaign(200001, 'bids sync');
        $campaign->setSeedNmId(111222333);
        $stubApi = new StubWbPromotionApiClient();
        $stubApi->fullstats = [
            new FullstatsCampaignDto(200001, 1000, 100, 10, '1000', '4000', [
                new FullstatsDayDto($date, 1000, 100, 10, '1000', '4000'),
            ]),
        ];
        $stubApi->normqueryStats = [
            new NormqueryClusterStatDto(200001, 111222333, 'кроссовки мужские', $date, 1000, 100, 10, '400'),
            new NormqueryClusterStatDto(200001, 111222333, 'кроссовки черные', $date, 800, 80, 2, '600'),
        ];
        $stubApi->normqueryBids = [
            new NormqueryClusterBidDto(200001, 111222333, 'кроссовки мужские', 5000),
            new NormqueryClusterBidDto(200001, 111222333, 'кроссовки черные', 8000),
        ];

        $service = $this->service($stubApi);
        $service->syncCampaign($campaign);

        $bids = $this->clusterBidsByNormQuery($campaign);
        $this->assertSame(5000, $bids['кроссовки мужские']);
        $this->assertSame(8000, $bids['кроссовки черные']);
        $this->assertSame([
            ['advertId' => 200001, 'nmId' => 111222333],
        ], $stubApi->getNormqueryBidsCalls);
    }

    public function testSyncCampaignCreatesClusterWithZeroBidBeforeGetBidsApplies(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');
        $campaign = new Campaign(200002, 'zero default');
        $campaign->setSeedNmId(111222333);
        $stubApi = new StubWbPromotionApiClient();
        $stubApi->fullstats = [
            new FullstatsCampaignDto(200002, 500, 50, 5, '250', '1000', [
                new FullstatsDayDto($date, 500, 50, 5, '250', '1000'),
            ]),
        ];
        $stubApi->normqueryStats = [
            new NormqueryClusterStatDto(200002, 111222333, 'кроссовки бег', $date, 500, 50, 5, '250'),
        ];
        $stubApi->normqueryBids = [
            new NormqueryClusterBidDto(200002, 111222333, 'кроссовки бег', 12000),
        ];

        $this->service($stubApi)->syncCampaign($campaign);

        $cluster = $campaign->getClusters()->first();
        $this->assertNotFalse($cluster);
        $this->assertSame(12000, $cluster->getCurrentBidKopecks());
    }

    public function testSyncCampaignKeepsExistingBidWhenGetBidsReturnsEmpty(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');
        $campaign = new Campaign(200003, 'keep bid');
        $cluster = new Cluster($campaign, 111222333, 'кроссовки бег', 15000);
        $campaign->addCluster($cluster);

        $stubApi = new StubWbPromotionApiClient();
        $stubApi->fullstats = [
            new FullstatsCampaignDto(200003, 500, 50, 5, '250', '1000', [
                new FullstatsDayDto($date, 500, 50, 5, '250', '1000'),
            ]),
        ];
        $stubApi->normqueryStats = [
            new NormqueryClusterStatDto(200003, 111222333, 'кроссовки бег', $date, 500, 50, 5, '250'),
        ];
        $stubApi->normqueryBids = [];

        $this->service($stubApi)->syncCampaign($campaign);

        $this->assertSame(15000, $cluster->getCurrentBidKopecks());
    }

    public function testSyncCampaignUpdatesBidOnResync(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');
        $campaign = new Campaign(200004, 'resync');
        $cluster = new Cluster($campaign, 111222333, 'кроссовки мужские', 5000);
        $campaign->addCluster($cluster);

        $stubApi = new StubWbPromotionApiClient();
        $stubApi->fullstats = [
            new FullstatsCampaignDto(200004, 1000, 100, 10, '1000', '4000', [
                new FullstatsDayDto($date, 1000, 100, 10, '1000', '4000'),
            ]),
        ];
        $stubApi->normqueryStats = [
            new NormqueryClusterStatDto(200004, 111222333, 'кроссовки мужские', $date, 1000, 100, 10, '400'),
        ];
        $stubApi->normqueryBids = [
            new NormqueryClusterBidDto(200004, 111222333, 'кроссовки мужские', 9500),
        ];

        $this->service($stubApi)->syncCampaign($campaign);

        $this->assertSame(9500, $cluster->getCurrentBidKopecks());
    }

    public function testSyncCampaignRequestsBidsForEachUniqueNmId(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');
        $campaign = new Campaign(200005, 'multi nm');
        $campaign->addCluster(new Cluster($campaign, 111, 'кластер a', 1000));
        $campaign->addCluster(new Cluster($campaign, 222, 'кластер b', 2000));

        $stubApi = new StubWbPromotionApiClient();
        $stubApi->fullstats = [
            new FullstatsCampaignDto(200005, 100, 10, 1, '100', '400', [
                new FullstatsDayDto($date, 100, 10, 1, '100', '400'),
            ]),
        ];
        $stubApi->normqueryStats = [
            new NormqueryClusterStatDto(200005, 111, 'кластер a', $date, 100, 10, 1, '50'),
            new NormqueryClusterStatDto(200005, 222, 'кластер b', $date, 100, 10, 1, '50'),
        ];

        $this->service($stubApi)->syncCampaign($campaign);

        $nmIds = array_column($stubApi->getNormqueryBidsCalls, 'nmId');
        sort($nmIds);
        $this->assertSame([111, 222], $nmIds);
    }

    public function testSyncCampaignIgnoresBidsForUnknownNormQueries(): void
    {
        $date = new \DateTimeImmutable('2026-07-21');
        $campaign = new Campaign(200006, 'unknown query');
        $cluster = new Cluster($campaign, 111222333, 'известный кластер', 3000);
        $campaign->addCluster($cluster);

        $stubApi = new StubWbPromotionApiClient();
        $stubApi->fullstats = [
            new FullstatsCampaignDto(200006, 100, 10, 1, '100', '400', [
                new FullstatsDayDto($date, 100, 10, 1, '100', '400'),
            ]),
        ];
        $stubApi->normqueryStats = [
            new NormqueryClusterStatDto(200006, 111222333, 'известный кластер', $date, 100, 10, 1, '50'),
        ];
        $stubApi->normqueryBids = [
            new NormqueryClusterBidDto(200006, 111222333, 'чужой кластер', 9999),
            new NormqueryClusterBidDto(200006, 111222333, 'известный кластер', 4500),
        ];

        $this->service($stubApi)->syncCampaign($campaign);

        $this->assertSame(4500, $cluster->getCurrentBidKopecks());
        $this->assertCount(1, $campaign->getClusters());
    }

    public function testSyncSkipsCampaignWithNonSyncableWbStatus(): void
    {
        $campaign = new Campaign(200007, 'paused status');
        $campaign->setSeedNmId(1);
        $campaign->setWbStatus(4); // ready, not in 7/9/11
        $stubApi = new StubWbPromotionApiClient();
        $stubApi->fullstats = [
            new FullstatsCampaignDto(200007, 1, 1, 1, '1', '1', []),
        ];

        $this->service($stubApi)->syncCampaign($campaign);

        $this->assertCount(0, $campaign->getClusters());
    }

    /**
     * @return array<string, int>
     */
    private function clusterBidsByNormQuery(Campaign $campaign): array
    {
        $bids = [];
        foreach ($campaign->getClusters() as $cluster) {
            $bids[$cluster->getNormQuery()] = $cluster->getCurrentBidKopecks();
        }

        return $bids;
    }

    private function service(StubWbPromotionApiClient $stubApi): WbStatsSyncService
    {
        $campaignStats = $this->createStub(CampaignDailyStatRepository::class);
        $campaignStats->method('findIndexedByDate')->willReturn([]);
        $clusterStats = $this->createStub(ClusterDailyStatRepository::class);
        $clusterStats->method('findIndexedByDate')->willReturn([]);

        return new WbStatsSyncService(
            $this->createStub(CampaignRepository::class),
            $campaignStats,
            $clusterStats,
            $stubApi,
            $this->createStub(EntityManagerInterface::class),
        );
    }
}

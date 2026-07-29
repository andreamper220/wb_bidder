<?php

namespace App\Tests\Integration;

use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Repository\CampaignRepository;
use App\Sync\WbStatsSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WbStatsSyncServiceTest extends KernelTestCase
{
    use DatabaseExtensionCheck;

    public function testSyncCampaignAppliesCurrentBidsFromMockApi(): void
    {
        $this->skipIfNoDatabaseDriver();
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $syncService = self::getContainer()->get(WbStatsSyncService::class);
        $campaignRepository = self::getContainer()->get(CampaignRepository::class);

        $wbAdvertId = random_int(930001, 939999);
        $campaign = new Campaign($wbAdvertId, 'sync bids integration');
        $em->persist($campaign);
        $em->flush();

        $syncService->syncCampaign($campaign);
        $em->clear();

        $reloaded = $campaignRepository->find($campaign->getId());
        $this->assertNotNull($reloaded);

        $bids = [];
        foreach ($reloaded->getClusters() as $cluster) {
            $bids[$cluster->getNormQuery()] = $cluster->getCurrentBidKopecks();
        }

        $this->assertSame(5000, $bids['кроссовки мужские'] ?? null);
        $this->assertSame(8000, $bids['кроссовки черные'] ?? null);
        $this->assertSame(12000, $bids['кроссовки бег'] ?? null);
    }

    public function testResyncUpdatesClusterBidsFromMockApi(): void
    {
        $this->skipIfNoDatabaseDriver();
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $syncService = self::getContainer()->get(WbStatsSyncService::class);
        $campaignRepository = self::getContainer()->get(CampaignRepository::class);

        $wbAdvertId = random_int(940001, 949999);
        $campaign = new Campaign($wbAdvertId, 'resync bids integration');
        $em->persist($campaign);
        $em->flush();

        $syncService->syncCampaign($campaign);

        $cluster = $campaign->getClusters()->first();
        $this->assertNotFalse($cluster);
        $cluster->setCurrentBidKopecks(1);
        $em->flush();

        $syncService->syncCampaign($campaign);
        $em->clear();

        $reloaded = $campaignRepository->find($campaign->getId());
        $this->assertNotNull($reloaded);

        $cluster = $reloaded->getClusters()->first();
        $this->assertNotFalse($cluster);
        $this->assertGreaterThan(1, $cluster->getCurrentBidKopecks());
    }

    public function testSyncKeepsManualBidWhenMockGetBidsHasNoMatchingCluster(): void
    {
        $this->skipIfNoDatabaseDriver();
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $syncService = self::getContainer()->get(WbStatsSyncService::class);

        $campaign = new Campaign(random_int(950001, 959999), 'manual bid keep');
        $cluster = new Cluster($campaign, 987654321, 'ручной кластер без фикстуры', 22222);
        $campaign->addCluster($cluster);
        $em->persist($campaign);
        $em->flush();

        $syncService->syncCampaign($campaign);
        $em->refresh($cluster);

        $this->assertSame(22222, $cluster->getCurrentBidKopecks());
    }
}

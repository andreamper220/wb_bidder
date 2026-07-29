<?php

namespace App\Tests\Integration;

use App\Bidding\Pipeline\BiddingPipeline;
use App\Entity\Campaign;
use App\Entity\CampaignDailyStat;
use App\Entity\Cluster;
use App\Entity\ClusterDailyStat;
use App\Enum\BidAction;
use App\Enum\CampaignMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BiddingPipelineTest extends KernelTestCase
{
    use DatabaseExtensionCheck;

    public function testDefensiveBlocksClusterUpWhenRoasLow(): void
    {
        $this->skipIfNoDatabaseDriver();
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pipeline = self::getContainer()->get(BiddingPipeline::class);

        $campaign = new Campaign(random_int(910001, 919999), 'integration test');
        $campaign->setBiddingEnabled(true);
        $campaign->setAttributionLagDays(0);
        $campaign->setRestrictUpIfRoasBelow('3.0');
        $campaign->setTargetCpa('200');
        $campaign->setMinOrders(3);
        $campaign->setMinImpressions(100);

        $cluster = new Cluster($campaign, 987654321, 'кроссовки бег', 10000);
        $campaign->addCluster($cluster);

        $em->persist($campaign);
        $em->flush();

        $date = new \DateTimeImmutable('today');
        $campaignStat = new CampaignDailyStat($campaign, $date);
        $campaignStat->setViews(1000)->setClicks(100)->setOrders(10)->setSpend('5000')->setRevenue('8000');
        $em->persist($campaignStat);

        $clusterStat = new ClusterDailyStat($cluster, $date);
        $clusterStat->setViews(500)->setClicks(50)->setOrders(10)->setSpend('500');
        $em->persist($clusterStat);
        $em->flush();

        $decisions = $pipeline->run($campaign, true);
        $this->assertNotEmpty($decisions);

        $decision = $decisions[0];
        $this->assertSame(CampaignMode::Defensive, $decision->getCampaignMode());
        $this->assertSame(BidAction::Up, $decision->getProposalAction());
        $this->assertSame(BidAction::Hold, $decision->getFinalAction());
    }

    public function testGrowthAllowsDownOnHighCpaCluster(): void
    {
        $this->skipIfNoDatabaseDriver();
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pipeline = self::getContainer()->get(BiddingPipeline::class);

        $campaign = new Campaign(random_int(920001, 929999), 'growth test');
        $campaign->setBiddingEnabled(true);
        $campaign->setAttributionLagDays(0);
        $campaign->setRestrictUpIfRoasBelow('1.0');
        $campaign->setAllowUpIfRoasAbove('2.0');
        $campaign->setTargetCpa('200');
        $campaign->setMinOrders(1);
        $campaign->setMinImpressions(100);

        $cluster = new Cluster($campaign, 1, 'кроссовки черные', 10000);
        $campaign->addCluster($cluster);

        $em->persist($campaign);
        $em->flush();

        $date = new \DateTimeImmutable('today');
        $campaignStat = new CampaignDailyStat($campaign, $date);
        $campaignStat->setViews(1000)->setClicks(100)->setOrders(10)->setSpend('1000')->setRevenue('5000');
        $em->persist($campaignStat);

        $clusterStat = new ClusterDailyStat($cluster, $date);
        $clusterStat->setViews(800)->setClicks(80)->setOrders(2)->setSpend('600');
        $em->persist($clusterStat);
        $em->flush();

        $decisions = $pipeline->run($campaign, true);
        $this->assertNotEmpty($decisions);

        $decision = $decisions[0];
        $this->assertSame(CampaignMode::Growth, $decision->getCampaignMode());
        $this->assertSame(BidAction::Down, $decision->getFinalAction());
    }
}

<?php

namespace App\Tests\Unit\Service;

use App\Entity\BidDecision;
use App\Entity\Campaign;
use App\Entity\Cluster;
use App\Enum\BidAction;
use App\Enum\BidDecisionStatus;
use App\Repository\BidDecisionRepository;
use App\Service\BidExecutionService;
use App\Tests\Support\StubWbPromotionApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class BidExecutionServiceTest extends TestCase
{
    public function testApplyUpSendsKopecksViaSetClusterBids(): void
    {
        $campaign = new Campaign(1, 'c');
        $cluster = new Cluster($campaign, 10, 'q', 10000);
        $campaign->addCluster($cluster);
        $this->setId($cluster, 5);

        $decision = new BidDecision($campaign, $cluster, BidAction::Up, 10000, 11500, 'test');
        $decision->setStatus(BidDecisionStatus::Pending);
        $this->setId($decision, 42);

        $stub = new StubWbPromotionApiClient();
        $this->service($stub, $decision)->apply(42);

        $this->assertSame(BidDecisionStatus::Applied, $decision->getStatus(), $decision->getReason());
        $this->assertSame([[
            'advertId' => 1,
            'nmId' => 10,
            'normQuery' => 'q',
            'bidKopecks' => 11500,
        ]], $stub->setClusterBidsCalls[0] ?? null);
        $this->assertSame(11500, $cluster->getCurrentBidKopecks());
        $this->assertFalse($cluster->isPaused());
    }

    public function testApplyPauseCallsDelete(): void
    {
        $campaign = new Campaign(1, 'c');
        $cluster = new Cluster($campaign, 10, 'q', 10000);
        $campaign->addCluster($cluster);
        $this->setId($cluster, 5);

        $decision = new BidDecision($campaign, $cluster, BidAction::Pause, 10000, 10000, 'pause');
        $decision->setStatus(BidDecisionStatus::Pending);
        $this->setId($decision, 7);

        $stub = new StubWbPromotionApiClient();
        $this->service($stub, $decision)->apply(7);

        $this->assertSame(BidDecisionStatus::Applied, $decision->getStatus(), $decision->getReason());
        $this->assertSame([[
            'advertId' => 1,
            'nmId' => 10,
            'normQuery' => 'q',
        ]], $stub->deleteClusterBidsCalls[0] ?? null);
        $this->assertTrue($cluster->isPaused());
    }

    public function testResumeFromPauseReAppliesBidOnHold(): void
    {
        $campaign = new Campaign(1, 'c');
        $cluster = new Cluster($campaign, 10, 'q', 10000);
        $cluster->setPaused(true);
        $campaign->addCluster($cluster);
        $this->setId($cluster, 5);

        $decision = new BidDecision($campaign, $cluster, BidAction::Hold, 10000, 10000, 'resume');
        $decision->setStatus(BidDecisionStatus::Pending);
        $this->setId($decision, 8);

        $stub = new StubWbPromotionApiClient();
        $this->service($stub, $decision)->apply(8);

        $this->assertSame(BidDecisionStatus::Applied, $decision->getStatus(), $decision->getReason());
        $this->assertNotEmpty($stub->setClusterBidsCalls);
        $this->assertFalse($cluster->isPaused());
    }

    private function service(StubWbPromotionApiClient $stub, BidDecision $decision): BidExecutionService
    {
        $repo = $this->createStub(BidDecisionRepository::class);
        $repo->method('find')->willReturn($decision);

        $snapshotRepo = $this->createStub(EntityRepository::class);
        $snapshotRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($snapshotRepo);

        return new BidExecutionService($repo, $stub, $em);
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}

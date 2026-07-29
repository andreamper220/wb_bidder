<?php

namespace App\Repository;

use App\Entity\Campaign;
use App\Entity\CampaignDailyStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CampaignDailyStat> */
class CampaignDailyStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CampaignDailyStat::class);
    }

  /** @return CampaignDailyStat[] */
    public function findForWindow(Campaign $campaign, int $windowDays): array
    {
        $from = new \DateTimeImmutable(sprintf('-%d days', $windowDays));

        return $this->createQueryBuilder('s')
            ->andWhere('s.campaign = :campaign')
            ->andWhere('s.date >= :from')
            ->setParameter('campaign', $campaign)
            ->setParameter('from', $from)
            ->getQuery()
            ->getResult();
    }
}

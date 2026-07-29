<?php

namespace App\Repository;

use App\Entity\Cluster;
use App\Entity\ClusterDailyStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClusterDailyStat> */
class ClusterDailyStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClusterDailyStat::class);
    }

  /** @return ClusterDailyStat[] */
    public function findForWindow(Cluster $cluster, int $windowDays): array
    {
        $from = new \DateTimeImmutable(sprintf('-%d days', $windowDays));

        return $this->createQueryBuilder('s')
            ->andWhere('s.cluster = :cluster')
            ->andWhere('s.date >= :from')
            ->setParameter('cluster', $cluster)
            ->setParameter('from', $from)
            ->getQuery()
            ->getResult();
    }
}

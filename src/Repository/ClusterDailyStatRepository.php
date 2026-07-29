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

    /**
     * @return ClusterDailyStat[]
     */
    public function findForWindow(
        Cluster $cluster,
        int $windowDays,
        int $attributionLagDays = 1,
        ?\DateTimeImmutable $now = null,
    ): array {
        $now ??= new \DateTimeImmutable('today');
        $to = $now->modify(sprintf('-%d days', $attributionLagDays));
        $from = $to->modify(sprintf('-%d days', max(0, $windowDays - 1)));

        return $this->createQueryBuilder('s')
            ->andWhere('s.cluster = :cluster')
            ->andWhere('s.date >= :from')
            ->andWhere('s.date <= :to')
            ->setParameter('cluster', $cluster)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<string> $dates Y-m-d
     *
     * @return array<string, ClusterDailyStat>
     */
    public function findIndexedByDate(Cluster $cluster, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.cluster = :cluster')
            ->andWhere('s.date IN (:dates)')
            ->setParameter('cluster', $cluster)
            ->setParameter('dates', $dates)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->getDate()->format('Y-m-d')] = $row;
        }

        return $indexed;
    }
}

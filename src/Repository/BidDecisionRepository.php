<?php

namespace App\Repository;

use App\Entity\BidDecision;
use App\Enum\BidDecisionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BidDecision> */
class BidDecisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BidDecision::class);
    }

    /** @return BidDecision[] */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('d')
            ->addSelect('camp', 'cl')
            ->join('d.campaign', 'camp')
            ->join('d.cluster', 'cl')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.status AS status, COUNT(d.id) AS cnt')
            ->groupBy('d.status')
            ->getQuery()
            ->getArrayResult();

        $result = ['total' => 0];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof BidDecisionStatus
                ? $row['status']->value
                : (string) $row['status'];
            $count = (int) $row['cnt'];
            $result[$status] = $count;
            $result['total'] += $count;
        }

        return $result;
    }
}

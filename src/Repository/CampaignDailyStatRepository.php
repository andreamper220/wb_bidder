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

    /**
     * Window of completed days excluding attribution lag at the end.
     *
     * @return CampaignDailyStat[]
     */
    public function findForWindow(
        Campaign $campaign,
        int $windowDays,
        int $attributionLagDays = 1,
        ?\DateTimeImmutable $now = null,
    ): array {
        $now ??= new \DateTimeImmutable('today');
        $to = $now->modify(sprintf('-%d days', $attributionLagDays));
        $from = $to->modify(sprintf('-%d days', max(0, $windowDays - 1)));

        return $this->createQueryBuilder('s')
            ->andWhere('s.campaign = :campaign')
            ->andWhere('s.date >= :from')
            ->andWhere('s.date <= :to')
            ->setParameter('campaign', $campaign)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<string> $dates Y-m-d
     *
     * @return array<string, CampaignDailyStat>
     */
    public function findIndexedByDate(Campaign $campaign, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.campaign = :campaign')
            ->andWhere('s.date IN (:dates)')
            ->setParameter('campaign', $campaign)
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

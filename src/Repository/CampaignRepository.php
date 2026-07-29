<?php

namespace App\Repository;

use App\Entity\Campaign;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Campaign> */
class CampaignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Campaign::class);
    }

    /** @return Campaign[] */
    public function findActiveForBidding(): array
    {
        return $this->findBy(['biddingEnabled' => true, 'active' => true]);
    }

    /** @return Campaign[] */
    public function findActive(): array
    {
        return $this->findBy(['active' => true]);
    }
}

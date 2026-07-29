<?php

namespace App\Entity;

use App\Repository\ClusterDailyStatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClusterDailyStatRepository::class)]
#[ORM\Table(name: 'cluster_daily_stats')]
#[ORM\UniqueConstraint(name: 'uniq_cluster_date', columns: ['cluster_id', 'date'])]
class ClusterDailyStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Cluster $cluster;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column]
    private int $views = 0;

    #[ORM\Column]
    private int $clicks = 0;

    #[ORM\Column]
    private int $orders = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $spend = '0.00';

    public function __construct(Cluster $cluster, \DateTimeImmutable $date)
    {
        $this->cluster = $cluster;
        $this->date = $date;
    }

    public function getCluster(): Cluster
    {
        return $this->cluster;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function setViews(int $views): self
    {
        $this->views = $views;

        return $this;
    }

    public function getClicks(): int
    {
        return $this->clicks;
    }

    public function setClicks(int $clicks): self
    {
        $this->clicks = $clicks;

        return $this;
    }

    public function getOrders(): int
    {
        return $this->orders;
    }

    public function setOrders(int $orders): self
    {
        $this->orders = $orders;

        return $this;
    }

    public function getSpend(): string
    {
        return $this->spend;
    }

    public function setSpend(string $spend): self
    {
        $this->spend = $spend;

        return $this;
    }
}

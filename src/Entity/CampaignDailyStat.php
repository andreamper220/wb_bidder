<?php

namespace App\Entity;

use App\Repository\CampaignDailyStatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampaignDailyStatRepository::class)]
#[ORM\Table(name: 'campaign_daily_stats')]
#[ORM\UniqueConstraint(name: 'uniq_campaign_date', columns: ['campaign_id', 'date'])]
class CampaignDailyStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Campaign $campaign;

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

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $revenue = '0.00';

    public function __construct(Campaign $campaign, \DateTimeImmutable $date)
    {
        $this->campaign = $campaign;
        $this->date = $date;
    }

    public function getCampaign(): Campaign
    {
        return $this->campaign;
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

    public function getRevenue(): string
    {
        return $this->revenue;
    }

    public function setRevenue(string $revenue): self
    {
        $this->revenue = $revenue;

        return $this;
    }
}

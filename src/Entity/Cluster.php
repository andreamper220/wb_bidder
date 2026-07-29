<?php

namespace App\Entity;

use App\Repository\ClusterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClusterRepository::class)]
#[ORM\Table(name: 'clusters')]
#[ORM\UniqueConstraint(name: 'uniq_campaign_norm_query', columns: ['campaign_id', 'norm_query'])]
class Cluster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'clusters')]
    #[ORM\JoinColumn(nullable: false)]
    private Campaign $campaign;

    #[ORM\Column]
    private int $nmId;

    #[ORM\Column(length: 500)]
    private string $normQuery;

    #[ORM\Column]
    private int $currentBidKopecks = 0;

    #[ORM\Column]
    private bool $paused = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastBidChangeAt = null;

    public function __construct(Campaign $campaign, int $nmId, string $normQuery, int $currentBidKopecks = 0)
    {
        $this->campaign = $campaign;
        $this->nmId = $nmId;
        $this->normQuery = $normQuery;
        $this->currentBidKopecks = $currentBidKopecks;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaign(): Campaign
    {
        return $this->campaign;
    }

    public function setCampaign(Campaign $campaign): self
    {
        $this->campaign = $campaign;

        return $this;
    }

    public function getNmId(): int
    {
        return $this->nmId;
    }

    public function getNormQuery(): string
    {
        return $this->normQuery;
    }

    public function getCurrentBidKopecks(): int
    {
        return $this->currentBidKopecks;
    }

    public function setCurrentBidKopecks(int $currentBidKopecks): self
    {
        $this->currentBidKopecks = $currentBidKopecks;

        return $this;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function setPaused(bool $paused): self
    {
        $this->paused = $paused;

        return $this;
    }

    public function getLastBidChangeAt(): ?\DateTimeImmutable
    {
        return $this->lastBidChangeAt;
    }

    public function setLastBidChangeAt(?\DateTimeImmutable $lastBidChangeAt): self
    {
        $this->lastBidChangeAt = $lastBidChangeAt;

        return $this;
    }

    public function __toString(): string
    {
        return $this->normQuery;
    }
}

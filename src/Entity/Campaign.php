<?php

namespace App\Entity;

use App\Repository\CampaignRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampaignRepository::class)]
#[ORM\Table(name: 'campaigns')]
class Campaign
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private int $wbAdvertId;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column]
    private bool $active = true;

  // Level 1 — Campaign ROAS
    #[ORM\Column]
    private bool $level1Enabled = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $targetRoas = '4.0000';

    #[ORM\Column]
    private int $metricsWindowDays = 7;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $restrictUpIfRoasBelow = '3.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private string $allowUpIfRoasAbove = '5.0000';

  // Level 2 — Cluster CPA
    #[ORM\Column]
    private bool $level2Enabled = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $targetCpa = '200.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $cpaBuffer = '20.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $pauseIfCpaAbove = '400.00';

    #[ORM\Column]
    private int $minOrders = 3;

    #[ORM\Column]
    private int $minImpressions = 100;

  // Shared guards
    #[ORM\Column]
    private int $minBidKopecks = 5000;

    #[ORM\Column]
    private int $maxBidKopecks = 50000;

    #[ORM\Column]
    private int $maxChangeUpPct = 10;

    #[ORM\Column]
    private int $maxChangeDownPct = 15;

    #[ORM\Column]
    private int $growthMaxChangeUpPct = 15;

    #[ORM\Column]
    private int $cooldownHours = 6;

    #[ORM\Column]
    private bool $dryRun = false;

    #[ORM\Column]
    private bool $biddingEnabled = false;

    /** @var Collection<int, Cluster> */
    #[ORM\OneToMany(mappedBy: 'campaign', targetEntity: Cluster::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $clusters;

    public function __construct(int $wbAdvertId, string $name = '')
    {
        $this->wbAdvertId = $wbAdvertId;
        $this->name = $name;
        $this->clusters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWbAdvertId(): int
    {
        return $this->wbAdvertId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isLevel1Enabled(): bool
    {
        return $this->level1Enabled;
    }

    public function setLevel1Enabled(bool $level1Enabled): self
    {
        $this->level1Enabled = $level1Enabled;

        return $this;
    }

    public function getTargetRoas(): string
    {
        return $this->targetRoas;
    }

    public function setTargetRoas(string $targetRoas): self
    {
        $this->targetRoas = $targetRoas;

        return $this;
    }

    public function getMetricsWindowDays(): int
    {
        return $this->metricsWindowDays;
    }

    public function setMetricsWindowDays(int $metricsWindowDays): self
    {
        $this->metricsWindowDays = $metricsWindowDays;

        return $this;
    }

    public function getRestrictUpIfRoasBelow(): string
    {
        return $this->restrictUpIfRoasBelow;
    }

    public function setRestrictUpIfRoasBelow(string $restrictUpIfRoasBelow): self
    {
        $this->restrictUpIfRoasBelow = $restrictUpIfRoasBelow;

        return $this;
    }

    public function getAllowUpIfRoasAbove(): string
    {
        return $this->allowUpIfRoasAbove;
    }

    public function setAllowUpIfRoasAbove(string $allowUpIfRoasAbove): self
    {
        $this->allowUpIfRoasAbove = $allowUpIfRoasAbove;

        return $this;
    }

    public function isLevel2Enabled(): bool
    {
        return $this->level2Enabled;
    }

    public function setLevel2Enabled(bool $level2Enabled): self
    {
        $this->level2Enabled = $level2Enabled;

        return $this;
    }

    public function getTargetCpa(): string
    {
        return $this->targetCpa;
    }

    public function setTargetCpa(string $targetCpa): self
    {
        $this->targetCpa = $targetCpa;

        return $this;
    }

    public function getCpaBuffer(): string
    {
        return $this->cpaBuffer;
    }

    public function setCpaBuffer(string $cpaBuffer): self
    {
        $this->cpaBuffer = $cpaBuffer;

        return $this;
    }

    public function getPauseIfCpaAbove(): ?string
    {
        return $this->pauseIfCpaAbove;
    }

    public function setPauseIfCpaAbove(?string $pauseIfCpaAbove): self
    {
        $this->pauseIfCpaAbove = $pauseIfCpaAbove;

        return $this;
    }

    public function getMinOrders(): int
    {
        return $this->minOrders;
    }

    public function setMinOrders(int $minOrders): self
    {
        $this->minOrders = $minOrders;

        return $this;
    }

    public function getMinImpressions(): int
    {
        return $this->minImpressions;
    }

    public function setMinImpressions(int $minImpressions): self
    {
        $this->minImpressions = $minImpressions;

        return $this;
    }

    public function getMinBidKopecks(): int
    {
        return $this->minBidKopecks;
    }

    public function setMinBidKopecks(int $minBidKopecks): self
    {
        $this->minBidKopecks = $minBidKopecks;

        return $this;
    }

    public function getMaxBidKopecks(): int
    {
        return $this->maxBidKopecks;
    }

    public function setMaxBidKopecks(int $maxBidKopecks): self
    {
        $this->maxBidKopecks = $maxBidKopecks;

        return $this;
    }

    public function getMaxChangeUpPct(): int
    {
        return $this->maxChangeUpPct;
    }

    public function setMaxChangeUpPct(int $maxChangeUpPct): self
    {
        $this->maxChangeUpPct = $maxChangeUpPct;

        return $this;
    }

    public function getMaxChangeDownPct(): int
    {
        return $this->maxChangeDownPct;
    }

    public function setMaxChangeDownPct(int $maxChangeDownPct): self
    {
        $this->maxChangeDownPct = $maxChangeDownPct;

        return $this;
    }

    public function getGrowthMaxChangeUpPct(): int
    {
        return $this->growthMaxChangeUpPct;
    }

    public function setGrowthMaxChangeUpPct(int $growthMaxChangeUpPct): self
    {
        $this->growthMaxChangeUpPct = $growthMaxChangeUpPct;

        return $this;
    }

    public function getCooldownHours(): int
    {
        return $this->cooldownHours;
    }

    public function setCooldownHours(int $cooldownHours): self
    {
        $this->cooldownHours = $cooldownHours;

        return $this;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    public function isBiddingEnabled(): bool
    {
        return $this->biddingEnabled;
    }

    public function setBiddingEnabled(bool $biddingEnabled): self
    {
        $this->biddingEnabled = $biddingEnabled;

        return $this;
    }

    /** @return Collection<int, Cluster> */
    public function getClusters(): Collection
    {
        return $this->clusters;
    }

    public function addCluster(Cluster $cluster): self
    {
        if (!$this->clusters->contains($cluster)) {
            $this->clusters->add($cluster);
            $cluster->setCampaign($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : (string) $this->wbAdvertId;
    }
}

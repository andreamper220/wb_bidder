<?php

namespace App\Entity;

use App\Enum\BidAction;
use App\Enum\BidDecisionStatus;
use App\Enum\CampaignMode;
use App\Repository\BidDecisionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BidDecisionRepository::class)]
#[ORM\Table(name: 'bid_decisions')]
class BidDecision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Campaign $campaign;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Cluster $cluster;

    #[ORM\Column(enumType: CampaignMode::class, nullable: true)]
    private ?CampaignMode $campaignMode = null;

    #[ORM\Column(enumType: BidAction::class, nullable: true)]
    private ?BidAction $proposalAction = null;

    #[ORM\Column(enumType: BidAction::class)]
    private BidAction $finalAction;

    #[ORM\Column]
    private int $oldBidKopecks;

    #[ORM\Column]
    private int $newBidKopecks;

    #[ORM\Column(type: Types::TEXT)]
    private string $reason;

    #[ORM\Column(enumType: BidDecisionStatus::class)]
    private BidDecisionStatus $status = BidDecisionStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $appliedAt = null;

    public function __construct(
        Campaign $campaign,
        Cluster $cluster,
        BidAction $finalAction,
        int $oldBidKopecks,
        int $newBidKopecks,
        string $reason,
    ) {
        $this->campaign = $campaign;
        $this->cluster = $cluster;
        $this->finalAction = $finalAction;
        $this->oldBidKopecks = $oldBidKopecks;
        $this->newBidKopecks = $newBidKopecks;
        $this->reason = $reason;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaign(): Campaign
    {
        return $this->campaign;
    }

    public function getCluster(): Cluster
    {
        return $this->cluster;
    }

    public function getCampaignMode(): ?CampaignMode
    {
        return $this->campaignMode;
    }

    public function setCampaignMode(?CampaignMode $campaignMode): self
    {
        $this->campaignMode = $campaignMode;

        return $this;
    }

    public function getProposalAction(): ?BidAction
    {
        return $this->proposalAction;
    }

    public function setProposalAction(?BidAction $proposalAction): self
    {
        $this->proposalAction = $proposalAction;

        return $this;
    }

    public function getFinalAction(): BidAction
    {
        return $this->finalAction;
    }

    public function getOldBidKopecks(): int
    {
        return $this->oldBidKopecks;
    }

    public function getNewBidKopecks(): int
    {
        return $this->newBidKopecks;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getStatus(): BidDecisionStatus
    {
        return $this->status;
    }

    public function setStatus(BidDecisionStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(?\DateTimeImmutable $appliedAt): self
    {
        $this->appliedAt = $appliedAt;

        return $this;
    }
}

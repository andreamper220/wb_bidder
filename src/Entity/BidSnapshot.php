<?php

namespace App\Entity;

use App\Repository\BidSnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BidSnapshotRepository::class)]
#[ORM\Table(name: 'bid_snapshots')]
class BidSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Campaign $campaign;

    /** @var array<string, array{normQuery: string, bidKopecks: int, paused: bool}> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, array{normQuery: string, bidKopecks: int, paused: bool}> $payload
     */
    public function __construct(Campaign $campaign, array $payload)
    {
        $this->campaign = $campaign;
        $this->payload = $payload;
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

    /**
     * @return array<string, array{normQuery: string, bidKopecks: int, paused: bool}>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

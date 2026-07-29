<?php

namespace App\Tests\Support;

use App\WbApi\Dto\FullstatsCampaignDto;
use App\WbApi\Dto\NormqueryClusterBidDto;
use App\WbApi\Dto\NormqueryClusterStatDto;
use App\WbApi\WbPromotionApiClient;

final class StubWbPromotionApiClient implements WbPromotionApiClient
{
    /** @var list<FullstatsCampaignDto> */
    public array $fullstats = [];

    /** @var list<NormqueryClusterStatDto> */
    public array $normqueryStats = [];

    /** @var list<NormqueryClusterBidDto> */
    public array $normqueryBids = [];

    /** @var list<array{advertId: int, nmId: int}> */
    public array $getNormqueryBidsCalls = [];

    /** @var list<array{advertId: int, nmId: int, normQuery: string, bidKopecks: int}> */
    public array $setClusterBidCalls = [];

    public function getFullstats(int $advertId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->fullstats;
    }

    public function getNormqueryStats(int $advertId, int $nmId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->normqueryStats;
    }

    public function getNormqueryBids(int $advertId, int $nmId): array
    {
        $this->getNormqueryBidsCalls[] = ['advertId' => $advertId, 'nmId' => $nmId];

        return $this->normqueryBids;
    }

    public function setClusterBid(int $advertId, int $nmId, string $normQuery, int $bidKopecks): void
    {
        $this->setClusterBidCalls[] = [
            'advertId' => $advertId,
            'nmId' => $nmId,
            'normQuery' => $normQuery,
            'bidKopecks' => $bidKopecks,
        ];
    }
}

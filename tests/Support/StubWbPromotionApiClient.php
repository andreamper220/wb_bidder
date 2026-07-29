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

    /** @var list<list<array{advertId: int, nmId: int, normQuery: string, bidKopecks: int}>> */
    public array $setClusterBidsCalls = [];

    /** @var list<list<array{advertId: int, nmId: int, normQuery: string}>> */
    public array $deleteClusterBidsCalls = [];

    public function getFullstats(array $advertIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->fullstats;
    }

    public function getNormqueryStats(array $items, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->normqueryStats;
    }

    public function getNormqueryBids(array $items): array
    {
        foreach ($items as $item) {
            $this->getNormqueryBidsCalls[] = $item;
        }

        return $this->normqueryBids;
    }

    public function setClusterBids(array $bids): void
    {
        $this->setClusterBidsCalls[] = $bids;
    }

    public function deleteClusterBids(array $bids): void
    {
        $this->deleteClusterBidsCalls[] = $bids;
    }
}

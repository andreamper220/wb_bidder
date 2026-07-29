<?php

namespace App\WbApi;

use App\WbApi\Dto\FullstatsCampaignDto;
use App\WbApi\Dto\NormqueryClusterBidDto;
use App\WbApi\Dto\NormqueryClusterStatDto;

interface WbPromotionApiClient
{
    /**
     * @param list<int> $advertIds max 50
     *
     * @return FullstatsCampaignDto[]
     */
    public function getFullstats(array $advertIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /**
     * @param list<array{advertId: int, nmId: int}> $items
     *
     * @return NormqueryClusterStatDto[]
     */
    public function getNormqueryStats(array $items, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /**
     * @param list<array{advertId: int, nmId: int}> $items
     *
     * @return NormqueryClusterBidDto[]
     */
    public function getNormqueryBids(array $items): array;

    /**
     * Sets cluster CPM bids in minor units (kopecks).
     *
     * POST /api/advert/v1/normquery/bids — bidMinorUnits, max 100 items.
     *
     * @param list<array{advertId: int, nmId: int, normQuery: string, bidKopecks: int}> $bids
     *
     * @see https://dev.wildberries.ru/openapi/promotion#tag/Poiskovye-klastery/operation/postV1NormqueryBids
     */
    public function setClusterBids(array $bids): void;

    /**
     * Removes custom cluster bids (pause).
     *
     * @param list<array{advertId: int, nmId: int, normQuery: string}> $bids
     *
     * @see https://dev.wildberries.ru/openapi/promotion (DELETE /adv/v0/normquery/bids)
     */
    public function deleteClusterBids(array $bids): void;
}

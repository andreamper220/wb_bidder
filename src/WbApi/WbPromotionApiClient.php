<?php

namespace App\WbApi;

use App\WbApi\Dto\FullstatsCampaignDto;
use App\WbApi\Dto\NormqueryClusterBidDto;
use App\WbApi\Dto\NormqueryClusterStatDto;

interface WbPromotionApiClient
{
    /** @return FullstatsCampaignDto[] */
    public function getFullstats(int $advertId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** @return NormqueryClusterStatDto[] */
    public function getNormqueryStats(int $advertId, int $nmId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** @return NormqueryClusterBidDto[] */
    public function getNormqueryBids(int $advertId, int $nmId): array;

    public function setClusterBid(int $advertId, int $nmId, string $normQuery, int $bidKopecks): void;
}

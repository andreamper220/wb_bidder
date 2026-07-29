<?php

namespace App\WbApi\Dto;

final readonly class NormqueryClusterBidDto
{
    public function __construct(
        public int $advertId,
        public int $nmId,
        public string $normQuery,
        public int $bidKopecks,
    ) {
    }
}

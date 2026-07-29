<?php

namespace App\WbApi\Dto;

final readonly class NormqueryClusterStatDto
{
    public function __construct(
        public int $advertId,
        public int $nmId,
        public string $normQuery,
        public \DateTimeImmutable $date,
        public int $views,
        public int $clicks,
        public int $orders,
        public string $spend,
    ) {
    }
}

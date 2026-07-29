<?php

namespace App\WbApi\Dto;

final readonly class FullstatsCampaignDto
{
    public function __construct(
        public int $advertId,
        public int $views,
        public int $clicks,
        public int $orders,
        public string $spend,
        public string $revenue,
        /** @var FullstatsDayDto[] */
        public array $days,
    ) {
    }
}

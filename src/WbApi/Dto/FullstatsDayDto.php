<?php

namespace App\WbApi\Dto;

final readonly class FullstatsDayDto
{
    public function __construct(
        public \DateTimeImmutable $date,
        public int $views,
        public int $clicks,
        public int $orders,
        public string $spend,
        public string $revenue,
    ) {
    }
}

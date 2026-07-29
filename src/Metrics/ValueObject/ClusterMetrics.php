<?php

namespace App\Metrics\ValueObject;

use App\Math\Bc;

final readonly class ClusterMetrics
{
    public function __construct(
        public int $impressions,
        public int $clicks,
        public int $orders,
        public string $spend,
        public int $windowDays,
    ) {
    }

    public function cpa(): ?string
    {
        if ($this->orders <= 0) {
            return null;
        }

        return Bc::div($this->spend, (string) $this->orders, 4);
    }
}

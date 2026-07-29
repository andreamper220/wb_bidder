<?php

namespace App\Metrics\ValueObject;

use App\Math\Bc;

final readonly class CampaignMetrics
{
    public function __construct(
        public int $impressions,
        public int $clicks,
        public int $orders,
        public string $spend,
        public string $revenue,
        public int $windowDays,
    ) {
    }

    public function roas(): ?string
    {
        if ((float) $this->spend <= 0) {
            return null;
        }

        return Bc::div($this->revenue, $this->spend, 4);
    }
}

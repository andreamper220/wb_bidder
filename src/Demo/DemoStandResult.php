<?php

namespace App\Demo;

use App\Enum\CampaignMode;

final readonly class DemoStandResult
{
    public function __construct(
        public int $campaignId,
        public string $campaignName,
        public int $decisionsCount,
        public bool $created,
        public bool $reset,
        public ?string $roas,
        public CampaignMode $campaignMode,
        public string $restrictUpIfRoasBelow,
    ) {
    }
}

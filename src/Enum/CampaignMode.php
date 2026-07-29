<?php

namespace App\Enum;

enum CampaignMode: string
{
    case Defensive = 'defensive';
    case Balanced = 'balanced';
    case Growth = 'growth';

    public function label(): string
    {
        return match ($this) {
            self::Defensive => 'Защитный (DEFENSIVE)',
            self::Balanced => 'Баланс (BALANCED)',
            self::Growth => 'Рост (GROWTH)',
        };
    }
}

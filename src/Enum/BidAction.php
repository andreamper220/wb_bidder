<?php

namespace App\Enum;

enum BidAction: string
{
    case Up = 'up';
    case Down = 'down';
    case Hold = 'hold';
    case Pause = 'pause';
}

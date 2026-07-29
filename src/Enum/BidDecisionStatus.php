<?php

namespace App\Enum;

enum BidDecisionStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Skipped = 'skipped';
    case Failed = 'failed';
}

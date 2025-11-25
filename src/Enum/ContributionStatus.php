<?php

declare(strict_types=1);

namespace App\Enum;

enum ContributionStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case ALREADY_EXISTS = 'ALREADY_EXISTS';
    case REJECTED = 'REJECTED';
}

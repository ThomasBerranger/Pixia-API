<?php

declare(strict_types=1);

namespace App\Enum;

enum TokenCost: int
{
    public const int CREATION_MINIMUM = 1000;

    case IDEA_ANALYSIS = 300;
    case IMAGE_GENERATION = 500;
    case IMAGE_UPLOAD = 200;
    case SUBMISSION = 100;
    case REFUND = 150;
}

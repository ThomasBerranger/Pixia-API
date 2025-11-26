<?php

declare(strict_types=1);

namespace App\DTO\Contribution;

use App\Enum\ContributionStatus;
use Symfony\Component\Validator\Constraints as Assert;

class ContributionUpdateRequest
{
    #[Assert\NotBlank(message: 'Un status est requis.')]
    public ContributionStatus $status;
}

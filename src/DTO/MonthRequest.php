<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class MonthRequest
{
    public function __construct(
        #[Assert\DateTime(
            format: 'Y-m-d',
            message: 'La date doit être au format YYYY-MM-DD'
        )]
        public ?string $month,
    )
    {
    }

    public function getMonth(): DateTimeImmutable
    {
        if ($this->month) {
            if ($date = DateTimeImmutable::createFromFormat('Y-m-d', $this->month)) {
                return $date;
            }
        }

        return new DateTimeImmutable('today');
    }
}

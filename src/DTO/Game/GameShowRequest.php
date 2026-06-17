<?php

declare(strict_types=1);

namespace App\DTO\Game;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class GameShowRequest
{
    public function __construct(
        #[Assert\DateTime(
            format: 'Y-m-d',
            message: 'La date doit être au format YYYY-MM-DD'
        )]
        public ?string $date,
    )
    {
    }

    public function getDate(): DateTimeImmutable
    {
        if ($this->date) {
            if ($date = DateTimeImmutable::createFromFormat('Y-m-d', $this->date)) {
                return $date;
            }
        }

        return new DateTimeImmutable('today');
    }
}

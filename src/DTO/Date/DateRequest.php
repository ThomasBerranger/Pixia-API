<?php

declare(strict_types=1);

namespace App\DTO\Date;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class DateRequest
{
    public function __construct(
        #[Assert\DateTime(format: 'Y-m-d', message: 'La date doit être au format YYYY-MM-DD')]
        public ?string $month = null,
    )
    {
    }

    public function getMonth(): DateTimeImmutable
    {
        return $this->month && ($date = DateTimeImmutable::createFromFormat('!Y-m-d', $this->month))
            ? $date
            : new DateTimeImmutable('today');
    }

    public static function toDateOrNull(?string $month): ?DateTimeImmutable
    {
        return $month !== null ? (new self($month))->getMonth() : null;
    }
}

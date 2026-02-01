<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class MonthRequest
{
    public function __construct(
        #[Assert\DateTime(format: 'Y-m-d', message: 'La date doit être au format YYYY-MM-DD')]
        public ?string $month,
    )
    {
    }

    #[Assert\Callback]
    public function validateNotFuture(ExecutionContextInterface $context): void
    {
        if ($this->getMonth() > new DateTimeImmutable('today')) {
            $context->buildViolation('La date ne peut pas être dans le futur ')
                ->atPath('month')
                ->addViolation();
        }
    }

    public function getMonth(): DateTimeImmutable
    {
        return $this->month && ($date = DateTimeImmutable::createFromFormat('!Y-m-d', $this->month))
            ? $date
            : new DateTimeImmutable('today');
    }
}

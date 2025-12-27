<?php

declare(strict_types=1);

namespace App\DTO\Game;

use App\Enum\GameType;
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

        public ?GameType $type,
    ) {
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date && DateTimeImmutable::createFromFormat('Y-m-d', $this->date) ?
            DateTimeImmutable::createFromFormat('Y-m-d', $this->date) :
            new DateTimeImmutable('today');
    }

    public function getType(): GameType
    {
        return $this->type ?? GameType::MULTIVERS;
    }
}

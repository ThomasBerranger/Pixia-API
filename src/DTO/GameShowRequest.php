<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\GameType;
use Symfony\Component\Validator\Constraints as Assert;

class GameShowRequest
{
    public function __construct(
        #[Assert\DateTime(
            format: 'Y-m-d',
            message: 'La date doit être au format YYYY-MM-DD'
        )]
        public ?string $date,

        public GameType $type = GameType::MULTIVERS,
    ) {
    }

    public function getDate(): \DateTime
    {
        return $this->date && \DateTime::createFromFormat('Y-m-d', $this->date) ?
            \DateTime::createFromFormat('Y-m-d', $this->date) :
            new \DateTime('today');
    }
}

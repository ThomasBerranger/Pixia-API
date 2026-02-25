<?php

declare(strict_types=1);

namespace App\Enum;

enum GameStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public static function fromMixed(mixed $value): self
    {
        return $value instanceof self ? $value : self::from($value);
    }
}

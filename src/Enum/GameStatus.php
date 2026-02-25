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
        if ($value instanceof self) {
            return $value;
        }

        if (!is_string($value) && !is_int($value)) {
            throw new \ValueError(sprintf('La valeur fournie n\'est pas un backing value valide pour %s.', self::class));
        }

        return self::from($value);
    }
}

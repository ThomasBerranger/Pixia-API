<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\TokenCost;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class TokenService
{
    public function removeTokens(User $user, TokenCost $cost): void
    {
        try {
            $user->removeTokens($cost->value);
        } catch (\LogicException) {
            throw new UnprocessableEntityHttpException('Tokens insuffisants');
        }
    }
}

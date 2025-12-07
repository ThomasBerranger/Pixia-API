<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\BadgeName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class UserService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function addPoints(User $user, Game $game): void
    {
        $points = $game->getDifficulty()->getPoints($user->getHintedGames()->contains($game));

        $user->setScore($user->getScore() + $points);

        $this->entityManager->flush();
    }

    public function findAndRewardSponsor(string $sponsorSlug): User
    {
        $sponsor = $this->entityManager->getRepository(User::class)->findOneBy(['slug' => $sponsorSlug]);

        if (!$sponsor) {
            throw new UnprocessableEntityHttpException('Sponsor not found');
        }

        $sponsor->setScore($sponsor->getScore() + 5);

        $sponsor->addBadge(new Badge(BadgeName::SPONSOR));

        if ($sponsor->getReferees()->count() >= 5) {
            $sponsor->addBadge(new Badge(BadgeName::SPONSOR_5));
        }

        return $sponsor;
    }

}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Contribution;
use App\Entity\User;
use App\Enum\Badge as BadgeEnum;
use App\Enum\ContributionStatus;
use App\Enum\ContributionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class ContributionService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function validate(Contribution $contribution): void
    {
        if ($this->entityManager->getRepository(Contribution::class)->findOneBy([
            'author' => $contribution->getAuthor(),
            'type' => $contribution->getType(),
            'status' => ContributionStatus::PENDING
        ])) {
            throw new UnprocessableEntityHttpException('Une proposition est déjà en cours de validation.');
        }
    }

    public function rewardPlayer(User $player, ContributionType $contributionType): void
    {
        $player->setScore($player->getScore() + 10);

        $badgeName = BadgeEnum::from($contributionType->value);

        if (!$this->entityManager->getRepository(Badge::class)->findOneBy(['player' => $player, 'name' => $badgeName])) {
            $badge = new Badge();
            $badge->setPlayer($player)->setName($badgeName);

            $this->entityManager->persist($badge);
            $this->entityManager->flush();
        }
    }
}

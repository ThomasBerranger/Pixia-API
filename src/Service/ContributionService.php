<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Contribution;
use App\Enum\ContributionStatus;
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
}

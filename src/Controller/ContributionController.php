<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ContributionRequest;
use App\Service\AuthenticatedUserService;
use App\Service\ContributionService;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contributions', name: 'contributions_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ContributionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        private readonly AuthenticatedUserService $authenticatedUserService,
        private readonly ContributionService      $contributionService,
        private readonly MailService              $mailService,
    )
    {
    }

    #[Route(name: 'create', methods: ['POST'], format: 'json')]
    public function create(#[MapRequestPayload] ContributionRequest $contributionRequest): Response
    {
        $author = $this->authenticatedUserService->getUserOrThrow();

        $contribution = $contributionRequest->toContribution();

        $contribution->setAuthor($author);

        $this->contributionService->validate($contribution);

        $this->entityManager->persist($contribution);
        $this->entityManager->flush();

        $this->mailService->sendContributionMail($author->getUsername(), $contribution);

        return $this->json($contribution, Response::HTTP_CREATED, context: ['groups' => ['contribution:show']]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Activity\ActivityIndexRequest;
use App\Enum\UserRole;
use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activities', name: 'activities_')]
#[IsGranted(UserRole::SUPER_ADMIN->value)]
final class ActivityController extends AbstractController
{
    private const int PER_PAGE = 20;

    public function __construct(
        private readonly ActivityRepository $activityRepository,
    )
    {
    }

    #[Route(name: 'index', methods: ['GET'], format: 'json')]
    public function index(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ?ActivityIndexRequest $request = null,
    ): JsonResponse
    {
        $request ??= new ActivityIndexRequest();

        $result = $this->activityRepository->findPaginated($request->search, $request->page, self::PER_PAGE);

        return $this->json(
            data: [
                'items' => $result['items'],
                'total' => $result['total'],
                'page' => $request->page,
                'perPage' => self::PER_PAGE,
            ],
            context: ['groups' => ['activity:index:super_admin']],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\NotificationRepository;
use App\Service\AuthenticatedUserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications', name: 'notifications_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly AuthenticatedUserService $authenticatedUserService,
        private readonly EntityManagerInterface   $entityManager,
        private readonly NotificationRepository   $notificationRepository,
    )
    {
    }

    #[Route(name: 'index', methods: ['GET'], format: 'json')]
    public function index(): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();
        $notifications = $this->notificationRepository->findAllByNewestFor($player);

        foreach ($notifications as $notification) {
            $isRead = $notification->getReaders()->contains($player);
            $notification->setRead($isRead);

            if (!$isRead) {
                $notification->addReader($player);
            }
        }

        $this->entityManager->flush();

        return $this->json($notifications, context: ['groups' => ['notification:index']]);
    }

    #[Route(path: '/has-unread', name: 'has-unread', methods: ['GET'], format: 'json')]
    public function hasUnread(): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();
        $notifications = $this->notificationRepository->findAllByNewestFor($player);

        // note $notification->getReaders()->contains($player) mérite un helper | Parmi les notif du joueur si notif not contains joueur dans reader -> notif non lue
        if (array_any($notifications, fn ($notification) => !$notification->getReaders()->contains($player))) {
            return $this->json(data: true);
        }

        return $this->json(data: false);
    }
}

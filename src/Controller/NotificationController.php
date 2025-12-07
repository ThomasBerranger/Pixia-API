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
        $user = $this->authenticatedUserService->getUserOrThrow();
        $notifications = $this->notificationRepository->findAllByNewest();

        foreach ($notifications as $notification) {
            $isRead = $notification->getReaders()->contains($user);
            $notification->setRead($isRead);

            if (!$isRead) {
                $notification->addReader($user);
            }
        }

        $this->entityManager->flush();

        return $this->json($notifications, context: ['groups' => ['notification:index']]);
    }

    #[Route(path: '/has-unread', name: 'has-unread', methods: ['GET'], format: 'json')]
    public function hasUnread(): JsonResponse
    {
        return $this->json(data: [
            'ok'
        ], context: ['groups' => ['user:index']]);
    }
}

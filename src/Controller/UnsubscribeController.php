<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class UnsubscribeController extends AbstractController
{
    public function __construct(
        private readonly UserRepository         $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UriSigner              $uriSigner,
        private readonly LoggerInterface        $logger,
        #[Autowire('%env(FRONTEND_URL)%')]
        private readonly string                 $frontendUrl,
    )
    {
    }

    #[Route('/unsubscribe', name: 'unsubscribe', methods: ['GET'])]
    public function unsubscribe(Request $request): Response
    {
        $id = $request->query->getString('id');

        if (!$this->uriSigner->checkRequest($request)) {
            return $this->fail('invalid signature', $id);
        }

        if (!Uuid::isValid($id)) {
            return $this->fail('invalid id', $id);
        }

        $user = $this->userRepository->find(Uuid::fromString($id));

        if ($user === null) {
            return $this->fail('user not found', $id);
        }

        $user->setEmailConsent(false);
        $this->entityManager->flush();

        return $this->redirect(rtrim($this->frontendUrl, '/') . '/?unsubscribed');
    }

    private function fail(string $reason, string $id): Response
    {
        $this->logger->error(sprintf('Unsubscribe failed (%s) for id: %s', $reason, $id));

        return new Response(status: Response::HTTP_BAD_REQUEST);
    }
}

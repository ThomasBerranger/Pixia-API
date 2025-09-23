<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users', name: 'user_')]
final class UserController extends AbstractController
{
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    #[IsGranted('view', subject: 'user')]
    public function index(User $user): Response
    {
        return $this->json(data: $user, context: ['groups' => 'user:read']);
    }
}

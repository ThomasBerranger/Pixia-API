<?php

declare(strict_types=1);

namespace App\Controller\Authentication;

use App\Attribute\RateLimit;
use App\DTO\Authentication\ForgotPasswordRequest;
use App\DTO\Authentication\ResetPasswordRequest;
use App\Entity\User;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface       $entityManager,
        private readonly MailService                  $mailService,
    )
    {
    }

    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    #[RateLimit(limiterName: 'auth_form')]
    public function forgotPassword(
        #[MapRequestPayload] ForgotPasswordRequest $request
    ): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $request->email]);

        if ($user) {
            try {
                $this->mailService->sendResetPasswordMail($user);
            } catch (ResetPasswordExceptionInterface|TransportExceptionInterface $e) {
            }
        }

        return $this->json([
            'message' => "Regarde dans tes mails, un lien t'a été envoyé !"
        ]);
    }

    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    #[RateLimit(limiterName: 'auth_form')]
    public function resetPassword(
        #[MapRequestPayload] ResetPasswordRequest $request,
        UserPasswordHasherInterface               $passwordHasher,
        JWTTokenManagerInterface                  $JWTTokenManager,
    ): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($request->token);
        } catch (ResetPasswordExceptionInterface $e) {
            return $this->json([
                'error' => $e->getMessage()
            ], 400);
        }

        $this->resetPasswordHelper->removeResetRequest($request->token);

        $user->setPassword($passwordHasher->hashPassword($user, $request->password));

        $this->entityManager->flush();

        return $this->json(['token' => $JWTTokenManager->create($user)]);
    }
}

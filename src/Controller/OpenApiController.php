<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\OpenAiRequest;
use App\Enum\UserRole;
use App\Service\OpenApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[Route('/open-api', name: 'open_api_')]
final class OpenApiController extends AbstractController
{
    public function __construct(private readonly OpenApiService $openApiService)
    {
    }

    #[Route('/generate-content', name: 'generate_content', methods: ['POST'], format: 'json')]
    #[IsGranted(UserRole::ADMIN->value)]
    public function generateGameContent(
        #[MapRequestPayload] OpenAiRequest $request,
    ): JsonResponse {
        try {
            $gameContent = $this->openApiService->requestGameContent($request->prompt);

            return $this->json($gameContent);
        } catch (\Exception|DecodingExceptionInterface|RedirectionExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $error) {
            return $this->json($error->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/generate-image', name: 'generate_image', methods: ['POST'], format: 'json')]
    #[IsGranted(UserRole::ADMIN->value)]
    public function generateGameImage(
        #[MapRequestPayload] OpenAiRequest $request,
    ): JsonResponse {
        return $this->json($this->openApiService->requestGameImage($request->prompt));
    }
}

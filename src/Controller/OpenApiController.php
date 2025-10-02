<?php

namespace App\Controller;

use App\DTO\OpenAiRequest;
use App\Service\OpenApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/generate-content', name: 'generate_content', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_ADMIN')]
    public function generateGameContent(
        #[MapRequestPayload] OpenAiRequest $request
    ): JsonResponse
    {
        return $this->json($this->openApiService->requestGameContent($request->prompt));
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/generate-image', name: 'generate_image', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_ADMIN')]
    public function generateGameImage(
        #[MapRequestPayload] OpenAiRequest $request
    ): JsonResponse
    {
        return $this->json($this->openApiService->requestGameImage($request->prompt));
    }
}

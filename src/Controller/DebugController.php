<?php

namespace App\Controller;

use App\Enum\GameType;
use App\Service\ImageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DebugController extends AbstractController
{
    public function __construct(
        private readonly ImageService $imageService,
        #[Autowire('%env(UPLOAD_IMAGES_DIR)%')]
        private string                $uploadImagesDir
    )
    {
    }

    #[Route('/debug/upload')]
    public function debugUpload(): JsonResponse
    {
        return $this->json([
            'uploadImagesDir' => $this->uploadImagesDir,
            'dir_exists' => is_dir($this->uploadImagesDir),
            'files' => glob($this->uploadImagesDir . '/**/*.{jpg,png,webp}', GLOB_BRACE),
            'sample_public_path' => $this->imageService->getDirectoryPath(GameType::MULTIVERS, new \DateTime()),
            'sample_write_path' => $this->imageService->getDirectoryPath(GameType::MULTIVERS, new \DateTime(), true),
        ]);
    }

    #[Route('/images/{path}', requirements: ['path' => '.+'])]
    public function serveImage(
        string $path,
        #[Autowire('%env(UPLOAD_IMAGES_DIR)%')] string $uploadDir
    ): BinaryFileResponse {
        $file = $uploadDir . '/' . $path;

        if (!file_exists($file)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($file);
    }
}

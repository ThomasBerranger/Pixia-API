<?php

namespace App\Service;

use App\DTO\GameUpdateImageRequest;
use App\Enum\GameType;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ImageService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        #[Autowire('%env(UPLOAD_IMAGES_DIR)%')]
        private string              $uploadImagesDir,
        #[Autowire('%env(PUBLIC_IMAGES_DIR)%')]
        private string              $publicImagesDir,
        private ValidatorInterface  $validator
    )
    {
    }

    public function downloadAndStore(GameType $gameType, DateTime $date, string $imageUrl): ?string
    {
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $directoryPath = $this->createRepository($gameType, $date);

        $imageName = $this->generateImageName();

        file_put_contents($directoryPath . '/' . $imageName, $this->getImage($imageUrl));

        return $imageName;
    }

    public function store(GameType $gameType, DateTime $date, ?string $image, UploadedFile $newImage): string
    {
        $directoryPath = $this->createRepository($gameType, $date);

        if ($image && file_exists($directoryPath . '/' . $image)) {
            unlink($directoryPath . '/' . $image);
        }

        $filename = $this->generateImageName();

        $newImage->move($directoryPath, $filename);

        return $filename;
    }

    private function createRepository(GameType $gameType, DateTime $date): string
    {
        $directoryPath = $this->getDirectoryPath($gameType, $date, isWriteOperation: true);

        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        return $directoryPath;
    }

    public function getDirectoryPath(GameType $gameType, DateTime $date, $isWriteOperation = false): string
    {
        return ($isWriteOperation ? $this->uploadImagesDir : $this->publicImagesDir) . '/' . $gameType->value . '/' . $date->format('Y/m');
    }

    private function getImage(string $url): string
    {
        try {
            $response = $this->httpClient->request('GET', $url);

            return $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to download image', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return '';
        }
    }

    private function generateImageName(): string
    {
        return uniqid() . '.jpg';
    }

    public function validate(UploadedFile $image): bool
    {
        $gameUpdateRequest = new GameUpdateImageRequest();
        $gameUpdateRequest->image = $image;

        $errors = $this->validator->validate($gameUpdateRequest);

        if (count($errors) > 0) {
            return false;
        }

        return true;
    }
}

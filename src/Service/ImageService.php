<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Game\GameUpdateImageRequest;
use App\Enum\GameType;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ImageService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        #[Autowire('%env(UPLOAD_IMAGES_DIR)%')]
        private string              $uploadImagesDir,
        private ValidatorInterface  $validator,
    )
    {
    }

    public function downloadAndStore(GameType $gameType, DateTimeImmutable $date, string $imageUrl, ?string $oldImage = null): ?string
    {
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $directoryPath = $this->createRepository($gameType, $date);

        if ($oldImage && file_exists($directoryPath . '/' . $oldImage)) {
            unlink($directoryPath . '/' . $oldImage);
        }

        $imageName = $this->generateImageName();

        $rawContent = $this->getImage($imageUrl);
        if ($rawContent === '') {
            return null;
        }
        $webpContent = $this->convertToWebp($rawContent);
        file_put_contents($directoryPath . '/' . $imageName, $webpContent);

        return $imageName;
    }

    public function store(GameType $gameType, DateTimeImmutable $date, ?string $image, mixed $newImage): string
    {
        if (!$newImage instanceof UploadedFile || !$this->validate($newImage)) {
            throw new UnprocessableEntityHttpException('Image introuvable ou non valide.');
        }

        $directoryPath = $this->createRepository($gameType, $date);

        if ($image && file_exists($directoryPath . '/' . $image)) {
            unlink($directoryPath . '/' . $image);
        }

        $filename = $this->generateImageName();

        $rawContent = file_get_contents($newImage->getPathname());
        if ($rawContent === false) {
            throw new \RuntimeException('Impossible de lire l\'image uploadée');
        }
        $webpContent = $this->convertToWebp($rawContent);
        file_put_contents($directoryPath . '/' . $filename, $webpContent);

        return $filename;
    }

    public function move(DateTimeImmutable $oldDate, GameType $gameType, DateTimeImmutable $newDate, string $imageName): ?string
    {
        $oldDirectoryPath = $this->getDirectoryPath($gameType, $oldDate, isWriteOperation: true);
        $oldFilePath = $oldDirectoryPath . '/' . $imageName;

        if ($oldDate->format('Y/m') === $newDate->format('Y/m')) {
            return $imageName;
        }

        $newDirectoryPath = $this->createRepository($gameType, $newDate);
        $newFilePath = $newDirectoryPath . '/' . $imageName;

        if (!rename($oldFilePath, $newFilePath)) {
            $this->logger->error('Failed to move image', [
                'from' => $oldFilePath,
                'to' => $newFilePath,
            ]);

            return null;
        }

        $this->cleanupEmptyDirectory($oldDirectoryPath);

        return $imageName;
    }

    private function cleanupEmptyDirectory(string $directoryPath): void
    {
        if (is_dir($directoryPath) && 2 === count(scandir($directoryPath))) {
            rmdir($directoryPath);
        }
    }

    private function createRepository(GameType $gameType, DateTimeImmutable $date): string
    {
        $directoryPath = $this->getDirectoryPath($gameType, $date, isWriteOperation: true);

        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        return $directoryPath;
    }

    public function getDirectoryPath(GameType $gameType, DateTimeImmutable $date, bool $isWriteOperation = false): string
    {
        $baseDir = $isWriteOperation ? $this->uploadImagesDir : 'images';

        return $baseDir . '/' . $gameType->value . '/' . $date->format('Y/m');
    }

    private function getImage(string $url): string
    {
        try {
            $response = $this->httpClient->request('GET', $url);

            return $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to download image', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function convertToWebp(string $rawContent): string
    {
        if ($this->isWebp($rawContent)) {
            return $rawContent;
        }

        $image = imagecreatefromstring($rawContent);

        if ($image === false) {
            throw new \RuntimeException('Impossible de décoder l\'image');
        }

        ob_start();
        imagewebp($image, null, 85);
        $webpContent = ob_get_clean();
        imagedestroy($image);

        if ($webpContent === false) {
            throw new \RuntimeException('Échec de la conversion en WebP');
        }

        return $webpContent;
    }

    private function isWebp(string $rawContent): bool
    {
        $info = getimagesizefromstring($rawContent);

        return $info !== false && $info[2] === IMAGETYPE_WEBP;
    }

    private function generateImageName(): string
    {
        return uniqid() . '.webp';
    }

    public function validate(UploadedFile $image): bool
    {
        $gameUpdateImageRequest = new GameUpdateImageRequest();
        $gameUpdateImageRequest->image = $image;

        $errors = $this->validator->validate($gameUpdateImageRequest);

        if (count($errors) > 0) {
            return false;
        }

        return true;
    }

    public function delete(GameType $gameType, DateTimeImmutable $date, string $imageName): bool
    {
        $directoryPath = $this->getDirectoryPath($gameType, $date, isWriteOperation: true);
        $filePath = $directoryPath . '/' . $imageName;

        if (file_exists($filePath)) {
            unlink($filePath);
            $this->cleanupEmptyDirectory($directoryPath);
            return true;
        }

        return false;
    }
}

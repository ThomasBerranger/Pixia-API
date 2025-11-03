<?php

namespace App\Service;

use App\Entity\Game;
use App\Enum\GameType;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ImageService
{
    public function __construct(private HttpClientInterface $httpClient, private LoggerInterface $logger)
    {
    }

    public function downloadAndStore(DateTime $date, GameType $gameType, string $url): string
    {
        $directoryPath = $this->createRepository($date, $gameType);

        $imageContent = $this->getImage($url);

        $fullFilePath = $directoryPath . '/' . $date->getTimestamp() . '.jpg';

        file_put_contents($fullFilePath, $imageContent);

        return $fullFilePath;
    }

    public function store(DateTime $date, GameType $gameType, UploadedFile $image): string
    {
        $directoryPath = $this->createRepository($date, $gameType);

        $filename = $date->getTimestamp() . '.jpg';

        // todo: créer une méthode pour move et stocker la précédente dans un autre dossier
        $image->move($directoryPath, $filename);

        return $directoryPath . '/' . $filename;
    }

    private function createRepository(DateTime $date, GameType $gameType): string
    {
        $directoryPath = 'images/' . $gameType->value . '/' . $date->format('Y/m');

        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        return $directoryPath;
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
}

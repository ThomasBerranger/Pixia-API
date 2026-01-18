<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\Game;

use App\DTO\Game\GameUpdateImageRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GameUpdateImageRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private string $tempDir;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);

        // Créer un répertoire temporaire pour les fichiers de test
        $this->tempDir = sys_get_temp_dir() . '/pixia_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Nettoyer les fichiers temporaires
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    // ========== Helpers pour créer des fichiers de test ==========

    private function createTestImage(string $mimeType, int $sizeInKb = 100): UploadedFile
    {
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $filename = 'test_image.' . $extension;
        $filepath = $this->tempDir . '/' . $filename;

        // Créer un fichier de la taille spécifiée
        $content = str_repeat('x', $sizeInKb * 1024);
        file_put_contents($filepath, $content);

        return new UploadedFile(
            $filepath,
            $filename,
            $mimeType,
            UPLOAD_ERR_OK,
            true // test mode
        );
    }

    // ========== Tests de validation complète ==========

    public function testImageRequired(): void
    {
        $dto = new GameUpdateImageRequest();
        // image reste null

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $imageErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'image'
        );

        $this->assertGreaterThan(0, $imageErrors);
        $this->assertStringContainsString('requise', array_values($imageErrors)[0]->getMessage());
    }

    public function testImageCannotBeNull(): void
    {
        $dto = new GameUpdateImageRequest();
        $dto->image = null;

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    // ========== Tests MIME types ==========

    public function testDTOAcceptsUploadedFileType(): void
    {
        $dto = new GameUpdateImageRequest();

        $this->assertNull($dto->image);

        // Le DTO accepte UploadedFile
        $filename = 'test.txt';
        $filepath = $this->tempDir . '/' . $filename;
        file_put_contents($filepath, 'test');

        $dto->image = new UploadedFile(
            $filepath,
            $filename,
            'text/plain',
            UPLOAD_ERR_OK,
            true
        );

        $this->assertInstanceOf(UploadedFile::class, $dto->image);
    }

    public function testInvalidMimeTypesRejected(): void
    {
        $dto = new GameUpdateImageRequest();

        $filename = 'test.txt';
        $filepath = $this->tempDir . '/' . $filename;
        file_put_contents($filepath, 'test content');

        $dto->image = new UploadedFile(
            $filepath,
            $filename,
            'text/plain',
            UPLOAD_ERR_OK,
            true
        );

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    // ========== Tests d'intégration ==========

    public function testImagePropertyIsNullableByDefault(): void
    {
        $dto = new GameUpdateImageRequest();

        $this->assertNull($dto->image);
    }

    public function testDTOConstraintsAreDefined(): void
    {
        // Vérifie que les contraintes sont bien définies
        $dto = new GameUpdateImageRequest();
        $dto->image = null;

        $errors = $this->validator->validate($dto);

        // Devrait avoir une erreur NotNull
        $this->assertGreaterThan(0, $errors);

        $firstError = $errors[0];
        $this->assertEquals('image', $firstError->getPropertyPath());
    }
}

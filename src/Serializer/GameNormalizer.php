<?php

namespace App\Serializer;

use App\Entity\Game;
use App\Enum\GameType;
use App\Service\ImageService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class GameNormalizer implements NormalizerInterface
{
    private const ALREADY_CALLED = 'GAME_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $objectNormalizer,
        private readonly ImageService $imageService,
    ) {
    }

    /**
     * @throws ExceptionInterface
     * @throws \Exception
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var array<string, mixed> $data */
        $data = $this->objectNormalizer->normalize($object, $format, $context);

        if (isset($data['image'], $data['type'], $data['date'])
            && is_string($data['image'])
            && '' !== $data['image']
            && is_string($data['type'])
            && is_string($data['date'])
        ) {
            $data['image'] = $this->imageService->getDirectoryPath(GameType::from($data['type']), new \DateTime($data['date'])).'/'.$data['image'];
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Game && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Game::class => true];
    }
}

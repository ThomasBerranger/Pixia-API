<?php

namespace App\Serializer;

use App\Entity\Game;
use App\Service\ImageService;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class GameNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'GAME_NORMALIZER_ALREADY_CALLED';

    public function __construct(private readonly ImageService $imageService)
    {
    }

    public function normalize($data, ?string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var Game $data */
        $array = $this->normalizer->normalize($data, $format, $context);

        if (isset($array['image']) && $array['image']) {
            $array['image'] = $this->imageService->getDirectoryPath($array['type'], $array['date']) . '/' . $array['image'];
        }

        return $array;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Game && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Game::class => true];
    }
}

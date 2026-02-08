<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Game;
use App\Enum\GameType;
use App\Security\Voter\GameVoter;
use App\Service\ImageService;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class GameNormalizer implements NormalizerInterface
{
    private const ALREADY_CALLED = 'GAME_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $objectNormalizer,
        private readonly ImageService        $imageService,
        private readonly Security            $security,
    )
    {
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

        $this->transformImagePath($data);
        $this->filterSensitiveFields($object, $data, $context);

        return $data;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function transformImagePath(array &$data): void
    {
        if (isset($data['image'], $data['type'], $data['date'])
            && is_string($data['image'])
            && '' !== $data['image']
            && is_string($data['type'])
            && is_string($data['date'])
        ) {
            $data['image'] = $this->imageService->getDirectoryPath(
                    GameType::from($data['type']),
                    new DateTimeImmutable($data['date'])
                ).'/'.$data['image'];
        }
    }

    private function filterSensitiveFields(Game $object, array &$data, array $context): void
    {
        $isGameStakeholder = $this->security->isGranted(GameVoter::VIEW, $object);

        if ($isGameStakeholder) {
            return;
        }

        $groups = $context['groups'] ?? [];

        if (!in_array('game:solution', $groups, true)) {
            unset($data['solution'], $data['englishSolution']);
        }

        if (!in_array('game:hint', $groups, true)) {
            unset($data['hint']);
        }
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

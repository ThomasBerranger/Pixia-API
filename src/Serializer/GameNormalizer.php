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
    public const SHOW_SOLUTION = 'show_solution';
    public const SHOW_HINT = 'show_hint';

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
        /**
         * @var Game $object
         * @var array<string, mixed> $context
         */
        $this->filterSensitiveFields($object, $data, $context);

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @throws \DateMalformedStringException
     */
    private function transformImagePath(array &$data): void
    {
        // Pas d'image → skip (cas légitime)
        if (!isset($data['image']) || !is_string($data['image']) || '' === $data['image']) {
            return;
        }

        // Image présente mais pas de type/date → bug de config!
        if (!isset($data['type'], $data['date'])) {
            throw new \LogicException(sprintf(
                'Cannot transform image path: missing %s in serialization groups. ' .
                'Ensure "type" and "date" are included in the same groups as "image".',
                !isset($data['type']) ? '"type"' : '"date"'
            ));
        }

        if (!is_string($data['type']) || !is_string($data['date'])) {
            throw new \LogicException('Type and date must be serialized as strings for image path transformation.');
        }

        $data['image'] = $this->imageService->getDirectoryPath(
            GameType::from($data['type']),
            new DateTimeImmutable($data['date'])
        ) . '/' . $data['image'];
    }

    /**
     * Deux modes
     * Gameplay : SHOW_SOLUTION/SHOW_HINT contrôlent l'affichage (révélation progressive)
     * Admin : permission VIEW requise, sinon tout est masqué (évite le spoil)
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    private function filterSensitiveFields(Game $object, array &$data, array $context): void
    {
        $isGameplayContext = array_key_exists(self::SHOW_SOLUTION, $context) || array_key_exists(self::SHOW_HINT, $context);

        // Permission VIEW → accès complet sans filtrage
        if (!$isGameplayContext && $this->security->isGranted(GameVoter::VIEW, $object)) {
            return;
        }

        // Masque solution sauf si explicitement autorisée
        if (!($context[self::SHOW_SOLUTION] ?? false)) {
            unset($data['solution'], $data['englishSolution']);
        }

        // Masque hint sauf si explicitement autorisé
        if (!($context[self::SHOW_HINT] ?? false)) {
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

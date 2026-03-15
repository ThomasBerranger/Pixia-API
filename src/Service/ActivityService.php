<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\API\MovieAnalysisResult;
use App\Entity\Activity;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\ActivityType;
use Doctrine\ORM\EntityManagerInterface;

readonly class ActivityService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function recordGameGenerationRequest(User $player, string $idea): void
    {
        $this->entityManager->persist(new Activity($player, ActivityType::GAME_GENERATION_REQUEST, [
            'idea' => $idea,
        ]));
    }

    public function recordGameGeneration(User $player, MovieAnalysisResult $analysis): void
    {
        $this->entityManager->persist(new Activity($player, ActivityType::GAME_GENERATION, [
            'status' => $analysis->status,
            'notorietyScore' => $analysis->notorietyScore,
            'films' => array_map(fn ($film) => [
                'titre' => $film->titre,
                'notorietyScore' => $film->notorietyScore,
            ], $analysis->films),
            'solution' => $analysis->solution,
            'englishSolution' => $analysis->englishSolution,
            'hint' => $analysis->hint,
            'imagePrompt' => $analysis->imagePrompt,
        ]));
    }

    public function recordGameGenerationError(User $player, string $idea, \Throwable $error): void
    {
        $this->entityManager->persist(new Activity($player, ActivityType::GAME_GENERATION_ERROR, [
            'idea' => $idea,
            'error' => $error->getMessage(),
        ]));
        $this->entityManager->flush();
    }

    public function recordImageGenerationRequest(User $player, Game $game, string $prompt): void
    {
        $this->entityManager->persist(new Activity($player, ActivityType::IMAGE_GENERATION_REQUEST, [
            'game_id' => $game->getId()?->toRfc4122(),
            'solution' => $game->getSolution(),
            'prompt' => $prompt,
        ]));
    }

    public function recordImageGeneration(User $player, Game $game, string $prompt, string $imageUrl): void
    {
        $this->entityManager->persist(new Activity($player, ActivityType::IMAGE_GENERATION, [
            'game_id' => $game->getId()?->toRfc4122(),
            'solution' => $game->getSolution(),
            'prompt' => $prompt,
            'imageUrl' => $imageUrl,
        ]));
    }

    public function recordAdminTokensClaim(User $player, int $amount): void
    {
        $this->entityManager->persist(new Activity($player, ActivityType::ADMIN_TOKENS_CLAIM, [
            'amount' => $amount,
        ]));
    }

    public function recordPlayerTokensClaim(User $player, int $amount): void
    {
        $this->entityManager->persist(new Activity($player, ActivityType::PLAYER_TOKENS_CLAIM, [
            'amount' => $amount,
        ]));
    }

    public function recordImageGenerationError(User $player, Game $game, \Throwable $error): void
    {
        $this->entityManager->persist(new Activity($player, ActivityType::IMAGE_GENERATION_ERROR, [
            'game_id' => $game->getId()?->toRfc4122(),
            'solution' => $game->getSolution(),
            'error' => $error->getMessage(),
        ]));
        $this->entityManager->flush();
    }
}

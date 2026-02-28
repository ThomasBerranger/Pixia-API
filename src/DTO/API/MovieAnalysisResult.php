<?php

declare(strict_types=1);

namespace App\DTO\API;

use App\Entity\Game;

readonly class MovieAnalysisResult
{
    /**
     * @param array<int, MovieAnalysisFilm> $films
     */
    public function __construct(
        public string $status,
        public float $notorietyScore,
        public array $films,
        public ?string $solution = null,
        public ?string $englishSolution = null,
        public ?string $hint = null,
        public ?string $imagePrompt = null,
    ) {
    }

    public function isRejected(): bool
    {
        return $this->solution === null || $this->imagePrompt === null;
    }

    public function getSolution(): string
    {
        return $this->solution ?? throw new \LogicException('Solution absente sur une analyse approved.');
    }

    public function getEnglishSolution(): string
    {
        return $this->englishSolution ?? throw new \LogicException('EnglishSolution absente sur une analyse approved.');
    }

    public function getHint(): string
    {
        return $this->hint ?? throw new \LogicException('Hint absent sur une analyse approved.');
    }

    public function getImagePrompt(): string
    {
        return $this->imagePrompt ?? throw new \LogicException('ImagePrompt absent sur une analyse approved.');
    }

    public function toGame(): Game
    {
        return new Game()
            ->setSolution($this->getSolution())
            ->setEnglishSolution($this->getEnglishSolution())
            ->setHint($this->getHint());
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawFilms = is_array($data['films'] ?? null) ? $data['films'] : [];
        $films = [];
        foreach ($rawFilms as $film) {
            if (is_array($film)) {
                $films[] = new MovieAnalysisFilm(
                    titre: is_string($film['titre'] ?? null) ? $film['titre'] : '',
                    notorietyScore: is_numeric($film['notorietyScore'] ?? null) ? (float) $film['notorietyScore'] : 0.0,
                );
            }
        }

        return new self(
            status: is_string($data['status'] ?? null) ? $data['status'] : '',
            notorietyScore: is_numeric($data['notorietyScore'] ?? null) ? (float) $data['notorietyScore'] : 0.0,
            films: $films,
            solution: is_string($data['solution'] ?? null) ? $data['solution'] : null,
            englishSolution: is_string($data['englishSolution'] ?? null) ? $data['englishSolution'] : null,
            hint: is_string($data['hint'] ?? null) ? $data['hint'] : null,
            imagePrompt: is_string($data['image_prompt'] ?? null) ? $data['image_prompt'] : null,
        );
    }
}

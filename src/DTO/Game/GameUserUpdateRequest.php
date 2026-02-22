<?php

declare(strict_types=1);

namespace App\DTO\Game;

use App\Entity\Game;
use App\Enum\GameStatus;
use Symfony\Component\Validator\Constraints as Assert;

class GameUserUpdateRequest
{
    #[Assert\NotBlank(message: 'La solution ne peut pas être vide')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'La solution doit faire au moins {{ limit }} caractères',
        maxMessage: 'La solution ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $solution;

    #[Assert\NotBlank(message: 'L\'indice ne peut pas être vide')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'L\'indice doit faire au moins {{ limit }} caractères',
        maxMessage: 'L\'indice ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $hint;

    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'image ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $image = null;

    #[Assert\Url(message: 'L\'URL de l\'image n\'est pas valide', requireTld: true)]
    public ?string $imageUrl = null;

    #[Assert\Choice(
        choices: [GameStatus::PENDING],
        message: 'Seul le statut PENDING est autorisé pour la soumission'
    )]
    public ?GameStatus $status = null;

    public function updateGame(Game $game): void
    {
        $game->setSolution($this->solution);
        $game->setHint($this->hint);

        if ($this->image !== null) {
            $game->setImage($this->image);
        }

        if ($this->imageUrl !== null) {
            $game->setImageUrl($this->imageUrl);
        }

        if ($this->status !== null) {
            $game->setStatus($this->status);
        }
    }
}

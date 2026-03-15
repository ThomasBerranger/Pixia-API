<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GameDifficulty;
use App\Repository\AchievementRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AchievementRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_game_player', columns: ['game_id', 'player_id'])]
#[ORM\Index(name: 'IDX_achievement_player', columns: ['player_id'])]
class Achievement
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['user:me'])]
    private DateTimeImmutable $date;

    #[ORM\Column(enumType: GameDifficulty::class)]
    private GameDifficulty $difficulty;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'achievements')]
    #[ORM\JoinColumn(name: 'player_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $player;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['user:me', 'user:show'])]
    private Game $game;

    public function __construct()
    {
        $this->difficulty = GameDifficulty::EASY;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getDifficulty(): GameDifficulty
    {
        return $this->difficulty;
    }

    public function setDifficulty(GameDifficulty $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getPlayer(): User
    {
        return $this->player;
    }

    public function setPlayer(User $player): static
    {
        $this->player = $player;

        return $this;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;

        return $this;
    }
}

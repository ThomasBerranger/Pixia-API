<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GameDifficulty;
use App\Enum\GameStatus;
use App\Enum\GameType;
use App\Repository\GameRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GameRepository::class)]
class Game
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['game:show', 'game:index', 'game:show:admin', 'game:index:admin'])]
    private ?Uuid $id = null;

    #[ORM\Column(enumType: GameType::class)]
    #[Groups(['game:show', 'game:index', 'game:show:admin', 'game:index:admin', 'user:show'])]
    private GameType $type = GameType::MULTIVERS;

    #[ORM\Column(length: 255)]
    #[Groups(['game:show', 'game:show:admin', 'game:index:admin', 'user:me'])]
    private string $solution;

    #[ORM\Column(length: 255)]
    #[Groups(['game:show', 'game:show:admin', 'game:index:admin'])]
    private string $hint;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['game:show', 'game:index', 'game:show:admin', 'game:index:admin', 'user:show'])]
    private DateTimeImmutable $date;

    #[ORM\Column(enumType: GameDifficulty::class)]
    #[Groups(['game:show', 'game:index', 'game:show:admin', 'game:index:admin'])]
    private GameDifficulty $difficulty = GameDifficulty::EASY;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['game:show:admin', 'game:index:admin'])]
    private User $author;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['game:show', 'game:index', 'game:show:admin', 'game:index:admin', 'user:show'])]
    private ?string $image;

    private ?string $imageUrl = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'hintedGames')]
    private Collection $hintedPlayers;

    #[ORM\Column(length: 255)]
    #[Groups(['game:show', 'game:show:admin'])]
    private string $englishSolution;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[Groups(['game:index:admin'])]
    private ?User $supervisor = null;

    #[ORM\Column(enumType: GameStatus::class)]
    #[Groups(['game:index', 'game:index:admin'])]
    private GameStatus $status = GameStatus::DRAFT;

    public function __construct()
    {
        $this->hintedPlayers = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getType(): GameType
    {
        return $this->type;
    }

    public function setType(GameType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSolution(): string
    {
        return $this->solution;
    }

    public function setSolution(string $solution): static
    {
        $this->solution = $solution;

        return $this;
    }

    public function getHint(): string
    {
        return $this->hint;
    }

    public function setHint(string $hint): static
    {
        $this->hint = $hint;

        return $this;
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

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getHintedPlayers(): Collection
    {
        return $this->hintedPlayers;
    }

    public function addHintedPlayer(User $hintedPlayer): static
    {
        if (!$this->hintedPlayers->contains($hintedPlayer)) {
            $this->hintedPlayers->add($hintedPlayer);
            $hintedPlayer->addHintedGame($this);
        }

        return $this;
    }

    public function removeHintedPlayer(User $hintedPlayer): static
    {
        if ($this->hintedPlayers->removeElement($hintedPlayer)) {
            $hintedPlayer->removeHintedGame($this);
        }

        return $this;
    }

    public function getEnglishSolution(): string
    {
        return $this->englishSolution;
    }

    public function setEnglishSolution(string $englishSolution): static
    {
        $this->englishSolution = $englishSolution;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSupervisor(): ?User
    {
        return $this->supervisor;
    }

    public function setSupervisor(?User $supervisor): static
    {
        $this->supervisor = $supervisor;

        return $this;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function setStatus(GameStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}

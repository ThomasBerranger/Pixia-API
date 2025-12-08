<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GameDifficulty;
use App\Enum\GameType;
use App\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_type_date', columns: ['type', 'date'])]
class Game
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['game:show', 'game:show:admin', 'game:index:admin'])]
    private ?Uuid $id = null;

    #[ORM\Column(enumType: GameType::class)]
    #[Groups(['game:show', 'game:show:admin', 'game:index:admin', 'user:show'])]
    private GameType $type;

    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    #[ORM\Column(length: 255)]
    #[Groups(['game:solution', 'game:show:admin', 'game:index:admin', 'achievement:index'])]
    private string $solution;

    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    #[ORM\Column(length: 255)]
    #[Groups(['game:hint', 'game:show:admin', 'game:index:admin'])]
    private string $hint;

    #[Assert\NotNull]
    #[Assert\Type(\DateTimeInterface::class)]
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['game:show', 'game:show:admin', 'game:index:admin', 'user:show'])]
    private \DateTime $date;

    #[Assert\NotBlank(message: 'Difficulty is required')]
    #[Assert\Type(type: GameDifficulty::class, message: 'Invalid difficulty value')]
    #[ORM\Column(enumType: GameDifficulty::class)]
    #[Groups(['game:show', 'game:show:admin', 'game:index:admin'])]
    private GameDifficulty $difficulty;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['game:show:admin', 'game:index:admin'])]
    private User $author;

    #[Assert\Length(min: 5, max: 255)]
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['game:show', 'game:show:admin', 'game:index:admin', 'user:show'])]
    private ?string $image;

    private string $imageUrl;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'hintedGames')]
    private Collection $hintedPlayers;

    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    #[ORM\Column(length: 255)]
    #[Groups(['game:solution', 'game:show:admin'])]
    private string $englishSolution;

    public function __construct()
    {
        $this->type = GameType::MULTIVERS;
        $this->difficulty = GameDifficulty::EASY;
        $this->hintedPlayers = new ArrayCollection();
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

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
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
}

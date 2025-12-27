<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MonthlyLeaderboardRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: MonthlyLeaderboardRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_player_month', columns: ['player_id', 'month'])]
class MonthlyLeaderboard
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'monthlyLeaderboards')]
    #[ORM\JoinColumn(nullable: false)]
    private User $player;

    #[ORM\Column]
    #[Groups(['user:me', 'user:index'])]
    private int $score;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['user:me'])]
    private DateTimeImmutable $month;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->score = 0;
        $this->updatedAt = new DateTimeImmutable('now');
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getMonth(): DateTimeImmutable
    {
        return $this->month;
    }

    public function setMonth(DateTimeImmutable $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

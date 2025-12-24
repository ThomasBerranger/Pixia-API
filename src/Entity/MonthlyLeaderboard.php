<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MonthlyLeaderboardRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MonthlyLeaderboardRepository::class)]
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
    private int $score;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $month;

    public function __construct()
    {
        $this->score = 0;
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
}

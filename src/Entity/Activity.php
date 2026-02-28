<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ActivityType;
use App\Repository\ActivityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
class Activity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $player;

    #[ORM\Column(enumType: ActivityType::class)]
    private ActivityType $type;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /** @param array<string, mixed>|null $data */
    public function __construct(User $player, ActivityType $type, ?array $data = null)
    {
        $this->player = $player;
        $this->type = $type;
        $this->data = $data;
        $this->createdAt = new DateTimeImmutable();
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

    public function getType(): ActivityType
    {
        return $this->type;
    }

    public function setType(ActivityType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getData(): ?array
    {
        return $this->data;
    }

    /** @param array<string, mixed>|null $data */
    public function setData(?array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

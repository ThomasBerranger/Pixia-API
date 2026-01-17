<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContributionStatus;
use App\Enum\ContributionType;
use App\Repository\ContributionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ContributionRepository::class)]
class Contribution
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['contribution:show'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'contributions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(length: 255)]
    #[Groups(['contribution:show', 'user:me'])]
    private string $title;

    #[ORM\Column(length: 1000)]
    #[Groups(['contribution:show'])]
    private string $description;

    #[ORM\Column(length: 255)]
    #[Groups(['contribution:show', 'user:me'])]
    private ContributionType $type;

    #[ORM\Column(length: 255)]
    #[Groups(['contribution:show', 'user:me'])]
    private ContributionStatus $status;

    #[ORM\Column]
    #[Groups(['contribution:show', 'user:me'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->status = ContributionStatus::PENDING;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getType(): ContributionType
    {
        return $this->type;
    }

    public function setType(ContributionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ContributionStatus
    {
        return $this->status;
    }

    public function setStatus(ContributionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}

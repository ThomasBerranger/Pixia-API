<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['user:index:admin'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['user:me', 'user:index:admin', 'game:index:admin'])]
    private string $email;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles;

    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    #[Groups(['user:me', 'user:show', 'user:index', 'user:index:admin'])]
    private int $score;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['user:me', 'user:show', 'user:index', 'user:index:admin', 'game:show:admin'])]
    private string $username;

    #[ORM\Column]
    #[Groups(['user:me', 'user:show', 'user:index:admin'])]
    private DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Badge>
     */
    #[ORM\OneToMany(targetEntity: Badge::class, mappedBy: 'player', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    #[Groups(['user:me', 'user:show', 'user:index'])]
    private Collection $badges;

    /**
     * @var Collection<int, Game>
     */
    #[ORM\ManyToMany(targetEntity: Game::class, inversedBy: 'hintedPlayers')]
    #[ORM\JoinTable(name: 'hint_request')]
    private Collection $hintedGames;

    /**
     * @var Collection<int, Contribution>
     */
    #[ORM\OneToMany(targetEntity: Contribution::class, mappedBy: 'author', cascade: ['remove'], orphanRemoval: true)]
    #[Groups(['user:me'])]
    private Collection $contributions;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['user:me', 'user:show', 'user:index'])]
    private ?string $avatarUrl = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['user:me', 'user:index'])]
    private string $slug;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'referees')]
    private ?self $sponsor = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'sponsor')]
    private Collection $referees;

    /**
     * @var Collection<int, Achievement>
     */
    #[ORM\OneToMany(targetEntity: Achievement::class, mappedBy: 'player', cascade: ['remove'], orphanRemoval: true)]
    #[Groups(['user:show'])]
    private Collection $achievements;

    /**
     * @var array<string, int>
     */
    #[Groups(['user:show', 'user:index'])]
    private array $streaks = ['current' => 0, 'best' => 0];

    /**
     * @var Collection<int, MonthlyLeaderboard>
     */
    #[ORM\OneToMany(targetEntity: MonthlyLeaderboard::class, mappedBy: 'player', orphanRemoval: true)]
    #[Groups(['user:me', 'user:index'])]
    private Collection $monthlyLeaderboards;

    #[Groups(['user:index'])]
    private ?int $rank = null;

    public function __construct()
    {
        $this->roles = [];
        $this->score = 0;
        $this->createdAt = new DateTimeImmutable();
        $this->badges = new ArrayCollection();
        $this->hintedGames = new ArrayCollection();
        $this->contributions = new ArrayCollection();
        $this->referees = new ArrayCollection();
        $this->achievements = new ArrayCollection();
        $this->monthlyLeaderboards = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     **/
    public function getUserIdentifier(): string
    {
        assert('' !== $this->email);

        return $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array)$this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $username = trim($username);

        $this->username = $username;
        $this->updateSlug();

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

    /**
     * @return Collection<int, Badge>
     */
    public function getBadges(): Collection
    {
        return $this->badges;
    }

    public function addBadge(Badge $badge): static
    {
        if (!$this->badges->exists(
            fn($key, Badge $existingBadge) => $existingBadge->getName() === $badge->getName()
        )) {
            $this->badges->add($badge);
            $badge->setPlayer($this);
        }

        return $this;
    }

    public function removeBadge(Badge $badge): static
    {
        $this->badges->removeElement($badge);

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * @return Collection<int, Game>
     */
    public function getHintedGames(): Collection
    {
        return $this->hintedGames;
    }

    public function addHintedGame(Game $hintedGame): static
    {
        if (!$this->hintedGames->contains($hintedGame)) {
            $this->hintedGames->add($hintedGame);
        }

        return $this;
    }

    public function removeHintedGame(Game $hintedGame): static
    {
        $this->hintedGames->removeElement($hintedGame);

        return $this;
    }

    /**
     * @return Collection<int, Contribution>
     */
    public function getContributions(): Collection
    {
        return $this->contributions;
    }

    public function addContribution(Contribution $contribution): static
    {
        if (!$this->contributions->contains($contribution)) {
            $this->contributions->add($contribution);
            $contribution->setAuthor($this);
        }

        return $this;
    }

    public function removeContribution(Contribution $contribution): static
    {
        $this->contributions->removeElement($contribution);

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function updateSlug(): void
    {
        $this->slug = mb_strtolower(str_replace([' ', '_'], '-', $this->username));
    }

    public function getSponsor(): ?self
    {
        return $this->sponsor;
    }

    public function setSponsor(?self $sponsor): static
    {
        $this->sponsor = $sponsor;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getReferees(): Collection
    {
        return $this->referees;
    }

    public function addReferee(self $referee): static
    {
        if (!$this->referees->contains($referee)) {
            $this->referees->add($referee);
            $referee->setSponsor($this);
        }

        return $this;
    }

    public function removeReferee(self $referee): static
    {
        if ($this->referees->removeElement($referee)) {
            // set the owning side to null (unless already changed)
            if ($referee->getSponsor() === $this) {
                $referee->setSponsor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Achievement>
     */
    public function getAchievements(): Collection
    {
        return $this->achievements;
    }

    /**
     * @return array<string, int>
     */
    public function getStreaks(): array
    {
        return $this->streaks;
    }

    /**
     * @param array<string, int> $streaks
     */
    public function setStreaks(array $streaks): static
    {
        $this->streaks = $streaks;

        return $this;
    }

    /**
     * @return Collection<int, MonthlyLeaderboard>
     */
    public function getMonthlyLeaderboards(): Collection
    {
        return $this->monthlyLeaderboards;
    }

    public function addMonthlyLeaderboard(MonthlyLeaderboard $monthlyLeaderboard): static
    {
        if (!$this->monthlyLeaderboards->contains($monthlyLeaderboard)) {
            $this->monthlyLeaderboards->add($monthlyLeaderboard);
            $monthlyLeaderboard->setPlayer($this);
        }

        return $this;
    }

    public function removeMonthlyLeaderboard(MonthlyLeaderboard $monthlyLeaderboard): static
    {
        $this->monthlyLeaderboards->removeElement($monthlyLeaderboard);

        return $this;
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(?int $rank): static
    {
        $this->rank = $rank;

        return $this;
    }
}

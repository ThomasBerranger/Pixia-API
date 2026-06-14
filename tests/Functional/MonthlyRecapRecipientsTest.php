<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MonthlyRecapRecipientsTest extends KernelTestCase
{
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private DateTimeImmutable $sentAfter;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->userRepository = $container->get(UserRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->sentAfter = new DateTimeImmutable('first day of this month midnight');
    }

    /**
     * @return list<string>
     */
    private function recipientEmails(): array
    {
        return array_map(
            static fn (User $u): string => $u->getEmail(),
            $this->userRepository->findEmailNotificationRecipients($this->sentAfter),
        );
    }

    private function user(string $email): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    public function testConsentingUsersAreIncludedAndBannedExcluded(): void
    {
        $emails = $this->recipientEmails();

        self::assertContains('test@test.com', $emails);
        self::assertContains('admin@test.com', $emails);
        self::assertContains('superadmin@test.com', $emails);
        self::assertNotContains('banned@test.com', $emails);
    }

    public function testBotIsExcluded(): void
    {
        // Les bots animent les leaderboards mais ne sont pas de vrais joueurs : jamais d'email.
        $bot = (new User())
            ->setEmail('bot@test.com')
            ->setUsername('bot-recipient')
            ->setPassword('x')
            ->setRoles([UserRole::BOT->value]);
        $this->entityManager->persist($bot);
        $this->entityManager->flush();

        self::assertNotContains('bot@test.com', $this->recipientEmails());
    }

    public function testUserWithoutConsentIsExcluded(): void
    {
        $user = $this->user('admin@test.com');
        $user->setEmailConsent(false);
        $this->entityManager->flush();

        self::assertNotContains('admin@test.com', $this->recipientEmails());
    }

    public function testUnsubscribedUserIsExcludedEvenWithPastSentAt(): void
    {
        // Regression guard: without parentheses around the OR, this user would be re-included
        // through the "monthlyRecapSentAt < sentAfter" branch despite having revoked consent.
        $user = $this->user('test@test.com');
        $user->setEmailConsent(false);
        $user->setMonthlyRecapSentAt(new DateTimeImmutable('first day of last month'));
        $this->entityManager->flush();

        self::assertNotContains('test@test.com', $this->recipientEmails());
    }

    public function testUserAlreadySentThisMonthIsExcluded(): void
    {
        $user = $this->user('admin@test.com');
        $user->setMonthlyRecapSentAt(new DateTimeImmutable('now'));
        $this->entityManager->flush();

        self::assertNotContains('admin@test.com', $this->recipientEmails());
    }

    public function testUserSentLastMonthIsIncludedAgain(): void
    {
        $user = $this->user('superadmin@test.com');
        $user->setMonthlyRecapSentAt(new DateTimeImmutable('first day of last month'));
        $this->entityManager->flush();

        self::assertContains('superadmin@test.com', $this->recipientEmails());
    }
}

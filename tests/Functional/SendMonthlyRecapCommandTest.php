<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SendMonthlyRecapCommandTest extends KernelTestCase
{
    private function tester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:mail:monthly-recap'));
    }

    private function userRepository(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }

    public function testSendMarksRecipientsAndIsIdempotentOnReRun(): void
    {
        self::markTestSkipped("Envoi désactivé tant que la feature notif n'est pas finalisée (cf. SendMonthlyRecapCommand).");

        // TODO (v finale notif) — réactiver quand l'envoi est rebranché dans la commande :
        // self::bootKernel();
        //
        // // Fixtures: test / admin / superadmin consent + non-bot/banned + jamais servis → 3 destinataires.
        // $tester = $this->tester();
        // $tester->execute([]);
        // $tester->assertCommandIsSuccessful();
        // self::assertStringContainsString('email(s) sent', $tester->getDisplay());
        //
        // // Effet de bord métier : chaque destinataire est marqué comme servi.
        // $user = $this->userRepository()->findOneBy(['email' => 'test@test.com']);
        // self::assertInstanceOf(User::class, $user);
        // self::assertNotNull($user->getMonthlyRecapSentAt());
        //
        // // Idempotence : un second run ne retrouve plus aucun destinataire → aucun doublon.
        // $rerun = $this->tester();
        // $rerun->execute([]);
        // $rerun->assertCommandIsSuccessful();
        // self::assertStringContainsString('No recipient', $rerun->getDisplay());
    }

    public function testDryRunSendsNothingAndDoesNotMarkRecipients(): void
    {
        self::bootKernel();

        $tester = $this->tester();
        $tester->execute(['--dry-run' => true]);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('DRY RUN', $tester->getDisplay());

        $user = $this->userRepository()->findOneBy(['email' => 'test@test.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertNull($user->getMonthlyRecapSentAt(), 'Dry run must not mark recipients as sent');
    }
}

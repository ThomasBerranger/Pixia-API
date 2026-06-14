<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\MonthlyLeaderboardRepository;
use App\Repository\UserRepository;
use App\Service\MonthlyRecapService;
// TODO (v finale notif) — réactiver avec l'envoi :
// use App\Service\MailService;
// use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mail:monthly-recap',
    description: 'Send the monthly game recap email to every consenting user (meant to run on the 1st of each month)',
)]
class SendMonthlyRecapCommand extends Command
{
    public function __construct(
        private readonly UserRepository               $userRepository,
        private readonly MonthlyLeaderboardRepository $monthlyLeaderboardRepository,
        private readonly MonthlyRecapService          $monthlyRecapService,
        // TODO (v finale notif) — réactiver avec l'envoi :
        // private readonly MailService                  $mailService,
        // private readonly EntityManagerInterface       $entityManager,
        private readonly ClockInterface               $clock,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List recipients and stats without sending any email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        $month = $this->clock->now()->modify('first day of last month');
        $sentAfter = $this->clock->now()->modify('first day of this month midnight');

        $recipients = $this->userRepository->findEmailNotificationRecipients($sentAfter);

        if (count($recipients) === 0) {
            $io->warning('No recipient to send the monthly recap to.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Monthly recap for %s — %d recipient(s)%s', $month->format('F Y'), count($recipients), $dryRun ? ' [DRY RUN]' : ''));

        $champion = $this->monthlyLeaderboardRepository->findChampionForMonth($month);

        foreach ($recipients as $recipient) {
            $recap = $this->monthlyRecapService->getRecapFor($recipient, $month, $champion?->getPlayer()->getUsername(), $champion?->getScore() ?? 0);

            $io->writeln(sprintf('%s — won: %d, points: %d', $recipient->getEmail(), $recap->won, $recap->points));

            // TODO (v finale notif) — réactiver l'envoi + le marquage idempotent :
            // try {
            //     $this->mailService->sendMonthlyRecapMail($recipient, $recap);
            //     $recipient->setMonthlyRecapSentAt($this->clock->now());
            //     $this->entityManager->flush();
            // } catch (\Throwable $e) {
            //     $io->error(sprintf('Failed to send to %s: %s', $recipient->getEmail(), $e->getMessage()));
            // }
        }

        $io->success(sprintf('%d recap(s) computed — sending is disabled (notif feature WIP).', count($recipients)));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\GameDifficulty;
use App\Enum\GameType;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Service\GameSolutionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:make-bots-play',
    description: 'This command make play every bot user in database',
)]
class PlayBotCommand extends Command
{
    public function __construct(
        private readonly UserRepository      $userRepository,
        private readonly GameRepository      $gameRepository,
        private readonly GameSolutionService $gameSolutionService,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $bots = $this->userRepository->findByRole('ROLE_BOT');


            $game = $this->gameRepository->findOneBy(['type' => GameType::MULTIVERS], ['date' => 'DESC']);

            if (!$game) {
                $io->error('No game found');

                return Command::FAILURE;
            }

            $successRate = match ($game->getDifficulty()) {
                GameDifficulty::HARD => 10,
                GameDifficulty::MEDIUM => 20,
                default => 50
            };

            foreach ($bots as $bot) {
                if (rand(1, 100) <= $successRate) {
                    $this->gameSolutionService->handleWin($game, $bot);
                }
            }

            $io->success('Well played bot army !');
        } catch (\Exception $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

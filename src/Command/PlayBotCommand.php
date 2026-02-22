<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\GameDifficulty;
use App\Enum\GameType;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Service\Game\GameSolutionService;
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

            $game = $this->gameRepository->findOneBy(
                ['type' => GameType::MULTIVERS, 'date' => new \DateTimeImmutable('today')],
            );

            if (!$game) {
                $io->warning('No game found');

                return Command::SUCCESS;
            }

            $successRate = match ($game->getDifficulty()) {
                GameDifficulty::EASY => 25,
                GameDifficulty::TRICKY => 20,
                GameDifficulty::MEDIUM => 15,
                GameDifficulty::HARD => 5,
                GameDifficulty::IMPOSSIBLE => 2,
            };

            $successCount = 0;

            foreach ($bots as $bot) {
                if (rand(1, 100) <= $successRate) {
                    $this->gameSolutionService->handleAnswer($game, $game->getSolution(), $bot);
                    $successCount++;
                }
            }

            $io->success($successCount > 0 ? $successCount . ' wins! Well played bot army!' : 'No win but nice try.');
        } catch (\Exception $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

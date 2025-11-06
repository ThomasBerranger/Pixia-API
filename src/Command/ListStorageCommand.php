<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:list-storage',
    description: 'Liste les fichiers dans le dossier de stockage spécifié',
)]
class ListStorageCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'Chemin du dossier à examiner',
                '/'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('directory');

        if (!is_dir($directory)) {
            $output->writeln("<error>Le dossier spécifié n'existe pas : $directory</error>");
            return Command::FAILURE;
        }

        $files = scandir($directory);

        if ($files === false) {
            $output->writeln("<error>Impossible de lire le dossier : $directory</error>");
            return Command::FAILURE;
        }

        $output->writeln("📂 Fichiers dans $directory :");
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $output->writeln(" - $file");
            }
        }

        return Command::SUCCESS;
    }
}

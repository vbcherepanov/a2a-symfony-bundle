<?php

declare(strict_types=1);

namespace A2A\Bundle\Command;

use A2A\Bundle\Storage\DoctrineTaskRepository;
use A2A\Storage\TaskRepository;
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command, Input\InputInterface, Output\OutputInterface};

#[AsCommand(name: 'a2a:storage:init', description: 'Create A2A tables when using Doctrine storage')]
final class StorageInitCommand extends Command
{
    public function __construct(private readonly TaskRepository $repository)
    {
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->repository instanceof DoctrineTaskRepository) {
            $this->repository->initializeSchema();
        }
        $output->writeln('A2A storage is ready.');
        return Command::SUCCESS;
    }
}

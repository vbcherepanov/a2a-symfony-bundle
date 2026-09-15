<?php

declare(strict_types=1);

namespace A2A\Bundle\Command;

use A2A\Transport\Grpc\OpenSwooleRuntime;
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command, Input\InputInterface, Output\OutputInterface};

#[AsCommand(name: 'a2a:grpc:serve', description: 'Run the configured A2A gRPC server')]
final class GrpcServeCommand extends Command
{
    public function __construct(private readonly OpenSwooleRuntime $runtime)
    {
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->runtime->serve();
        return Command::SUCCESS;
    }
}

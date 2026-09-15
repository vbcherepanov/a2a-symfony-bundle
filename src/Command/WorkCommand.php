<?php

declare(strict_types=1);

namespace A2A\Bundle\Command;

use A2A\Server\{Processor, ServerOptions};
use Symfony\Component\Console\{Attribute\AsCommand, Command\Command, Input\InputInterface, Input\InputOption, Output\OutputInterface};

#[AsCommand(name: 'a2a:work', description: 'Process durable A2A tasks and webhook deliveries')]
final class WorkCommand extends Command
{
    public function __construct(private readonly Processor $processor, private readonly ServerOptions $options)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Process the current queue and exit');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        do {
            $this->processor->workOnce();
            if ($input->getOption('once')) {
                return Command::SUCCESS;
            }
            usleep((int) ($this->options->pollIntervalSeconds * 1000000));
        } while (true);
    }
}

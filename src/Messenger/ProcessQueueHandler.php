<?php

declare(strict_types=1);

namespace A2A\Bundle\Messenger;

use A2A\Server\Processor;

final readonly class ProcessQueueHandler
{
    public function __construct(private Processor $processor)
    {
    }
    public function __invoke(ProcessQueue $message): void
    {
        $this->processor->workOnce();
    }
}

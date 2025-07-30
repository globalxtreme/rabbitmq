<?php

namespace GlobalXtreme\RabbitMQ\Commands;

use GlobalXtreme\RabbitMQ\Queue\GXAsyncWorkflowConsumer;
use Illuminate\Console\Command;

class AsyncWorkflowCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:async-workflow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Default command for async workflow executor (consumer)';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $consumer = new GXAsyncWorkflowConsumer();

        $consumer->setQueues([
            // Your queueName => executorClass
        ]);

        $this->line("\n<bg=blue>[GX-Info]</> Processing executor for the <options=bold>[async-workflow]</> connection.\n");

        $consumer->consume();
    }
}

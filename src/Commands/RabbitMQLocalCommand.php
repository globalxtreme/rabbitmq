<?php

namespace GlobalXtreme\RabbitMQ\Commands;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQConsumer;
use Illuminate\Console\Command;

class RabbitMQLocalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:consumer-local';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Default command for consume local connection';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $consumer = new GXRabbitMQConsumer();

        $consumer->setExchanges([
            // Your exchangeName => consumerClass
        ]);

        $consumer->setQueues([
            // Your queueName => consumerClass
        ]);

        $connection = GXRabbitConnectionType::LOCAL;
        $this->line("\n<bg=blue>[GX-Info]</> Processing consumer for the <options=bold>[$connection]</> connection.\n");

        $consumer->consume($connection);
    }
}

<?php

namespace GlobalXtreme\RabbitMQ\Commands;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQConsumer;
use Illuminate\Console\Command;

class RabbitMQGlobalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:consumer-global';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Default command for consume global connection';

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

        $connection = GXRabbitConnectionType::GLOBAL;
        $this->line("\n<bg=blue>[GX-Info]</> Processing consumer for the <options=bold>[$connection]</> connection.\n");

        $consumer->rabbitmqConsume($connection);
    }
}

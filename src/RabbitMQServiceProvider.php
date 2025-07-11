<?php

namespace GlobalXtreme\RabbitMQ;

use Illuminate\Support\ServiceProvider;

class RabbitMQServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/gx-rabbitmq.php' => config_path('gx-rabbitmq.php'),
        ], 'gx-rabbitmq-config');

        $this->publishes([
            __DIR__.'/Commands/RabbitMQGlobalCommand.php' => app_path('/Console/Commands/MessageBroker/RabbitMQGlobalCommand.php'),
            __DIR__.'/Commands/RabbitMQLocalCommand.php' => app_path('/Console/Commands/MessageBroker/RabbitMQLocalCommand.php'),
        ], 'gx-rabbitmq-command');
    }

    public function register()
    {
        $this->commands(
            \GlobalXtreme\RabbitMQ\Commands\RabbitMQConsumerMakeCommand::class,
            \GlobalXtreme\RabbitMQ\Commands\AsyncWorkflowConsumerMakeCommand::class,
        );
    }
}

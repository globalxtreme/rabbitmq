<?php

namespace GlobalXtreme\RabbitMQ;

use Illuminate\Support\ServiceProvider;

class RabbitMQServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/gx-rabbitmq' => config_path('gx-rabbitmq.php'),
        ], 'gx-rabbitmq-config');

        $this->publishes([
            __DIR__.'/Commands/RabbitMQGlobalCommand' => app_path('/Console/Commands/MessageBroker/RabbitMQGlobalCommand.php'),
            __DIR__.'/Commands/RabbitMQLocalCommand' => app_path('/Console/Commands/MessageBroker/RabbitMQLocalCommand.php'),
        ], 'gx-rabbitmq-command');
    }
}

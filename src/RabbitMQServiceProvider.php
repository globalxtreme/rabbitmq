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

        $this->loadRoutesFrom(__DIR__.'/../src/Http/routes.php');
    }

    public function register()
    {
        //
    }
}

<?php

namespace GlobalXtreme\RabbitMQ\Queue\Traits;

trait GXRabbitMQDispatchable
{
    public static function dispatch(array|string $message)
    {
        return new GXRabbitMQManager($message);
    }
}

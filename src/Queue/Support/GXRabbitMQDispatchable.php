<?php

namespace GlobalXtreme\RabbitMQ\Queue\Support;

trait GXRabbitMQDispatchable
{
    public static function dispatch(array|string $message)
    {
        return new GXRabbitMQManager($message);
    }
}

<?php

namespace GlobalXtreme\RabbitMQ\Queue\Support;

use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;

trait GXRabbitMQDispatchable
{
    public static function dispatch(array|string $message, GXRabbitMessage|int|null $queueMessage = null)
    {
        return new GXRabbitMQManager($message, $queueMessage);
    }
}

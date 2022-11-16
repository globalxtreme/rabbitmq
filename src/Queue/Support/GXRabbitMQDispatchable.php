<?php

namespace GlobalXtreme\RabbitMQ\Queue\Support;

trait GXRabbitMQDispatchable
{
    public static function dispatch(array|string $message, int|null $queueMessageId = null)
    {
        return new GXRabbitMQManager($message, $queueMessageId);
    }
}

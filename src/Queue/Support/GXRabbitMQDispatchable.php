<?php

namespace GlobalXtreme\RabbitMQ\Queue\Support;

trait GXRabbitMQDispatchable
{
    /**
     * @param array|string $message
     * @param int|null $queueMessageId
     *
     * @return GXRabbitMQManager
     */
    public static function dispatch($message, $queueMessageId = null)
    {
        return new GXRabbitMQManager($message, $queueMessageId);
    }
}

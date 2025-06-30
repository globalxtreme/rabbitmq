<?php

namespace GlobalXtreme\RabbitMQ\Queue\Support;

use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;

trait GXRabbitMQDispatchable
{
    /**
     * @param array|string $message
     * @param GXRabbitMessage|int|null $queueMessage
     *
     * @return GXRabbitMQManager
     */
    public static function dispatch(array|string $message, GXRabbitMessage|int|null $queueMessage = null)
    {
        return new GXRabbitMQManager($message, $queueMessage);
    }
}

<?php

namespace GlobalXtreme\RabbitMQ\Constant;

use GlobalXtreme\RabbitMQ\Consumer\SaveFailedMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitKey;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitQueue;

class GXRabbitKeyConstant
{
    const MAX_RETRY = 10;

    const MESSAGES = [];


    /**
     * @param GXRabbitMessage $message
     * @param string $key
     * @param string|null $queue
     *
     * @return mixed|null
     */
    public static function callMessageClass(GXRabbitMessage $message, string $key, string|null $queue = null)
    {
        if (isset(static::MESSAGES[$key]) && static::MESSAGES[$key]) {
            $class = static::MESSAGES[$key];
        } else {

            $queue = GXRabbitQueue::ofName($queue ?: config('queue.connections.rabbitmq.queue'))->first();
            if (!$queue) {
                return null;
            }

            $rabbitKey = GXRabbitKey::ofQueueId($queue->id)
                ->ofName($key)
                ->first();
            if (!$rabbitKey) {
                return null;
            }

            $class = $rabbitKey->class;
        }

        return new $class($message);
    }

}

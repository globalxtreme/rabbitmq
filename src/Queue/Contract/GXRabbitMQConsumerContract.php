<?php

namespace GlobalXtreme\RabbitMQ\Queue\Contract;

use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;

interface GXRabbitMQConsumerContract
{
    /**
     * The service for handle process of message
     * Please don't use try catch. For handle failed process in BaseQueueJob
     *
     * @param GXRabbitMessage $message
     * @param array|string $data
     *
     * @return array|null
     */
    public static function consume($message, $data);
}

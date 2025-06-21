<?php

namespace GlobalXtreme\RabbitMQ\Queue\Contract;

interface GXRabbitMQConsumerContract
{
    /**
     * The service for handle process of message
     * Please don't use try catch. For handle failed process in BaseQueueJob
     *
     * @param array|string $data
     *
     * @return void
     */
    public static function consume($data);
}

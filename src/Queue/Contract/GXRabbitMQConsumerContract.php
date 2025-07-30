<?php

namespace GlobalXtreme\RabbitMQ\Queue\Contract;

interface GXRabbitMQConsumerContract
{
    /**
     * @return array|null
     */
    public function consume();

}

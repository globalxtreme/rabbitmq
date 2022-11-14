<?php

use GlobalXtreme\RabbitMQ\Constant\GXRabbitKeyConstant;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQQueue;

if (!function_exists("failed_message_broker")) {

    /**
     * @param string $exchange
     * @param string $queue
     * @param string $key
     * @param array|string $message
     * @param Exception $exception
     * @param int|null $failedId
     *
     * @return void
     */
    function failed_message_broker(string $exchange, string $queue, string $key, array|string $message, Exception $exception, int|null $failedId = null)
    {
        GXRabbitMQQueue::dispatch($message)
            ->onExchange($exchange, true)->onQueue($queue)->onKey(GXRabbitKeyConstant::FAILED_SAVE)
            ->onFailedId($failedId)->onFailedKey($key)->onException($exception);
    }

}

if (!function_exists("success_repair_message_broker")) {

    /**
     * @param int $failedId
     * @param string $queue
     * @param string $key
     *
     * @return void
     */
    function success_repair_message_broker(int $failedId, string $queue, string $key)
    {
        GXRabbitMQQueue::dispatch("Repair is successfully")
            ->onExchange('failed')->onQueue($queue)->onKey(GXRabbitKeyConstant::FAILED_SAVE)
            ->onFailedId($failedId)->onFailedKey($key, true);
    }

}

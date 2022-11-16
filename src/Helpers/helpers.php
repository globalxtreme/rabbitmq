<?php

use GlobalXtreme\RabbitMQ\Constant\GXRabbitKeyConstant;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQQueue;

if (!function_exists("failed_message_broker")) {

    /**
     * @param string $exchange
     * @param string $queue
     * @param string $key
     * @param array|string $message
     * @param Exception|string|null $exception
     * @param int|null $messageId
     * @param int|null $failedId
     *
     * @return void
     */
    function failed_message_broker(string $exchange, string $queue, string $key, array|string $message,
                                   Exception|string|null $exception = null, int|null $messageId = null, int|null $failedId = null)
    {
        GXRabbitMQQueue::dispatch($message, $messageId)
            ->onExchange($exchange, true)->onQueue($queue)->onKey(GXRabbitKeyConstant::FAILED_SAVE)
            ->onFailedId($failedId)->onFailedKey($key)->onException($exception);
    }

}

if (!function_exists("success_repair_message_broker")) {

    /**
     * @param int $messageId
     * @param int $failedId
     * @param string $queue
     * @param string $key
     *
     * @return void
     */
    function success_repair_message_broker(int $messageId, int $failedId, string $queue, string $key)
    {
        GXRabbitMQQueue::dispatch("Repair is successfully", $messageId)
            ->onExchange('failed')->onQueue($queue)->onKey(GXRabbitKeyConstant::FAILED_SAVE)
            ->onFailedId($failedId)->onFailedKey($key, true);
    }

}
<?php

use GlobalXtreme\RabbitMQ\Queue\Contract\GXRabbitMQConsumerContract;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQConsumer;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQPublish;
use Illuminate\Support\Facades\Log;

class Example
{
    public function publish()
    {
        $queues = ['inventory-test1', 'inventory-test2'];
        foreach ($queues as $queue) {
            GXRabbitMQPublish::dispatch(['message' => "hallow Queue? $queue"])
                ->onQueue($queue);
        }

        $exchanges = ['business.product.justification', 'business.product.variant'];
        foreach ($exchanges as $exchange) {
            GXRabbitMQPublish::dispatch(['message' => "hallow Exchange? $exchange"])
                ->onExchange($exchange);
        }
    }

    public function consume()
    {
        $consumer = new GXRabbitMQConsumer();

        $consumer->setExchanges([
            'business.product.justification' => TestingOneConsumer::class,
            'business.product.variant' => TestingTwoConsumer::class,
        ]);

        $consumer->setQueues([
            'inventory-test1' => TestingOneConsumer::class,
            'inventory-test2' => TestingTwoConsumer::class,
        ]);

        $consumer->consume();
    }

}

class TestingOneConsumer implements GXRabbitMQConsumerContract
{
    public static function consume(array|string $data)
    {
        Log::info("Testing One");
        Log::info($data);
        Log::info("=========");
    }
}

class TestingTwoConsumer implements GXRabbitMQConsumerContract
{
    public static function consume(array|string $data)
    {
        Log::info("Testing two");
        Log::info($data);
        Log::info("=========");
    }
}

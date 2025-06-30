<?php

use GlobalXtreme\RabbitMQ\Queue\Contract\GXRabbitMQConsumerContract;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQConsumer;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQPublish;
use Illuminate\Support\Facades\Log;

class Example
{
    public function publish()
    {
        $queues = ['business.product.variant.justification.create.queue', 'business.notification.employee.push.queue'];
        foreach ($queues as $queue) {
            GXRabbitMQPublish::dispatch(['message' => "hallow Queue? $queue"])
                ->onQueue($queue);
        }

        $exchanges = ['business.product.variant.justification.approval.exchange', 'business.product.variant.update.exchange'];
        foreach ($exchanges as $exchange) {
            GXRabbitMQPublish::dispatch(['message' => "hallow Exchange? $exchange"])
                ->onExchange($exchange)
                ->onDelivery('customers')
                ->onDelivery('inventories', true);
        }
    }

    public function consume()
    {
        $consumer = new GXRabbitMQConsumer();

        $consumer->setExchanges([
            'business.product.variant.justification.approval.exchange' => TestingOneConsumer::class,
            'business.product.variant.update.exchange' => TestingTwoConsumer::class,
        ]);

        $consumer->setQueues([
            'business.product.variant.justification.create.queue' => TestingOneConsumer::class,
            'business.notification.employee.push.queue-' => TestingTwoConsumer::class,
        ]);

        $consumer->rabbitmqConsume();
    }

}

class TestingOneConsumer implements GXRabbitMQConsumerContract
{
    /**
     * @param array|string $data
     *
     * @return array|null
     */
    public static function consume(array|string $data)
    {
        Log::info("Testing One");
        Log::info($data);
        Log::info("=========");

        return [
            'name' => 'John Doe',
        ];
    }
}

class TestingTwoConsumer implements GXRabbitMQConsumerContract
{
    /**
     * @param array|string $data
     *
     * @return array|null
     */
    public static function consume(array|string $data)
    {
        Log::info("Testing two");
        Log::info($data);
        Log::info("=========");

        return [
            'success' => true
        ];
    }
}

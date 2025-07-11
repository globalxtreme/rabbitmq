<?php

use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Queue\Contract\GXRabbitMQConsumerContract;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQConsumer;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQPublish;
use Illuminate\Support\Facades\Log;

class RabbitMQExample
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
    protected $message;
    protected $payload;

    /**
     * @param GXRabbitMessage $message
     * @param array $payload
     */
    public function __construct(GXRabbitMessage $message, array $payload)
    {
        $this->message = $message;
        $this->payload = $payload;
    }


    /**
     * @return array|null
     */
    public function consume()
    {
        Log::info("Testing One");
        Log::info($this->payload);
        Log::info("=========");

        return [
            'name' => 'John Doe',
        ];
    }
}

class TestingTwoConsumer implements GXRabbitMQConsumerContract
{
    protected $message;
    protected $payload;

    /**
     * @param GXRabbitMessage $message
     * @param array $payload
     */
    public function __construct(GXRabbitMessage $message, array $payload)
    {
        $this->message = $message;
        $this->payload = $payload;
    }


    /**
     * @return array|null
     */
    public function consume()
    {
        Log::info("Testing two");
        Log::info($this->payload);
        Log::info("=========");

        return [
            'success' => true
        ];
    }
}

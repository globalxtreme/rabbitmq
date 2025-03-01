<?php

namespace GlobalXtreme\RabbitMQ\Queue;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Models\GXRabbitConnection;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessageFailed;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class GXRabbitMQConsumer
{
    /**
     * @var array
     */
    protected $configuration = [];

    /**
     * @var array
     */
    protected $exchanges = [], $queues = [];

    /**
     * @var GXRabbitConnection|null
     */
    protected $GXRabbitConnection;


    /** --- SETTER --- */

    /**
     * @param array $exchanges
     *
     * @return void
     */
    public function setExchanges(array $exchanges)
    {
        foreach ($exchanges as $exchange => $consumer) {
            $this->exchanges[$exchange] = $consumer;
        }
    }

    /**
     * @param array $queues
     *
     * @return void
     */
    public function setQueues(array $queues)
    {
        foreach ($queues as $queue => $consumer) {
            $this->queues[$queue] = $consumer;
        }
    }


    /** --- ACTION --- */

    public function consume(string $connectionType = GXRabbitConnectionType::GLOBAL)
    {
        $configuration = config("gx-rabbitmq.connection.types.$connectionType");

        $this->GXRabbitConnection = GXRabbitConnection::where('connection', $connectionType)
            ->where(function ($query) use ($connectionType) {
                if ($connectionType != GXRabbitConnectionType::GLOBAL) {
                    $query->where('service', config('base.conf.service'));
                }
            })->first();
        throw_if(!$this->GXRabbitConnection, new \Exception("Connection $connectionType does not exists"));

        $connection = new AMQPStreamConnection(
            $configuration['host'],
            $configuration['port'],
            $configuration['user'],
            $configuration['password'],
            '/',
            false,
            'AMQPLAIN',
            null,
            'en_US',
            (config('gx-rabbitmq.timeout') ?: 60)
        );
        $channel = $connection->channel();

        foreach ($this->exchanges ?: [] as $exchange => $consumer) {
            $channel->exchange_declare($exchange, 'fanout', false, true, false);

            list($queueName, ,) = $channel->queue_declare("", false, false, true, false);

            $channel->queue_bind($queueName, $exchange);

            $channel->basic_consume($queueName, '', false, false, false, false, function ($msg) use ($channel, $exchange, $consumer) {
                $this->processMessage($exchange, "", $consumer, $msg);
                $channel->basic_ack($msg->delivery_info['delivery_tag']);
            });
        }

        foreach ($this->queues ?: [] as $queue => $consumer) {
            $channel->queue_declare($queue, false, true, false, false);

            $channel->basic_qos(0, 1, false);

            $channel->basic_consume($queue, '', false, false, false, false, function ($msg) use ($channel, $queue, $consumer) {
                $this->processMessage("", $queue, $consumer, $msg);
                $channel->basic_ack($msg->delivery_info['delivery_tag']);
            });
        }

        register_shutdown_function(function ($channel, $connection) {
            $channel->close();
            $connection->close();
        }, $channel, $connection);

        try {
            while (count($channel->callbacks)) {
                $channel->wait();
            }
        } catch (\Throwable $exception) {
            Log::error($exception);
        }
    }


    /** --- FUNCTIONS --- */

    private function processMessage($exchange, $queue, $consumer, $message)
    {
        Log::info("RABBITMQ-CONSUMING: $consumer " . now()->format('Y-m-d H:i:s'));

        $messageId = null;
        $data = null;

        try {

            $body = json_decode($message->getBody(), true);
            $messageId = $body['messageId'];
            $data = $body['data'];

            $queueMessage = GXRabbitMessage::find($body['messageId']);
            if (!$queueMessage) {
                $this->failedConsuming($consumer, $exchange, $queue, $messageId, $data, "Message Not found [{$body['messageId']}]");
                return;
            }

            $consumer::consume($data);

            $this->successConsuming($consumer, $queueMessage);

        } catch (\Exception $exception) {
            $this->failedConsuming($consumer, $exchange, $queue, $messageId, $data, $exception->getMessage());
            Log::error($exception);
        }
    }

    private function failedConsuming($consumer, $exchange, $queue, $messageId, $data, $exception)
    {
        Log::error("RABBITMQ-FAILED: $consumer " . now()->format('Y-m-d H:i:s'));

        if ($messageId) {
            if ($exception instanceof \Exception) {
                $exceptionAttribute = [
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ];
            } else {
                $exceptionAttribute = ['message' => $exception, 'trace' => ''];
            }

            GXRabbitMessageFailed::create([
                'connectionId' => $this->GXRabbitConnection->id,
                'messageId' => $messageId,
                'service' => config('base.conf.service'),
                'exchange' => $exchange,
                'queue' => $queue,
                'payload' => $data,
                'exception' => $exceptionAttribute,
            ]);
        }
    }

    private function successConsuming($consumer, $queueMessage)
    {
        $queueMessage->finished = true;
        $queueMessage->save();

        Log::info("RABBITMQ-SUCCESS: $consumer " . now()->format('Y-m-d H:i:s'));
    }

}

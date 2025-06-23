<?php

namespace GlobalXtreme\RabbitMQ\Queue;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Constant\GXRabbitMessageDeliveryStatus;
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
    protected array $configuration = [];

    /**
     * @var array
     */
    protected array $exchanges = [], $queues = [];

    /**
     * @var GXRabbitConnection|null
     */
    protected GXRabbitConnection|null $GXRabbitConnection;


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
            connection_timeout: (config('gx-rabbitmq.timeout') ?: 60)
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

        try {
            $channel->consume();
        } catch (\Throwable $exception) {
            Log::error($exception);
        } finally {
            $channel->close();
            $connection->close();
        }
    }


    /** --- FUNCTIONS --- */

    private function processMessage($exchange, $queue, $consumer, $message)
    {
        Log::info("RABBITMQ-CONSUMING: $consumer " . now()->format('Y-m-d H:i:s'));

        $queueMessage = null;
        $messageId = null;
        $data = null;

        try {

            $body = json_decode($message->getBody(), true);
            $messageId = $body['messageId'];
            $data = $body['data'];

            $messageQuery = GXRabbitMessage::query();
            if ($serviceName = config('base.conf.service')) {
                $messageQuery->with([
                    'deliveries' => function ($query) use ($serviceName) {
                        $query->where('consumerService', $serviceName);
                    }
                ]);
            }

            $queueMessage = $messageQuery->find($body['messageId']);
            if (!$queueMessage) {
                $this->failedConsuming($consumer, $exchange, $queue, $messageId, $data, "Message Not found [{$body['messageId']}]");
                return;
            }

            $response = $consumer::consume($data);

            $this->successConsuming($consumer, $queueMessage, $response);

        } catch (\Throwable $throwable) {
            if (!$queueMessage) {
                $queueMessage = $messageId;
            }

            $this->failedConsuming($consumer, $exchange, $queue, $queueMessage, $data, $throwable);
            Log::error($throwable);
        }
    }

    private function failedConsuming($consumer, $exchange, $queue, $message, $data, \Throwable|string $throwable)
    {
        Log::error("RABBITMQ-FAILED: $consumer " . now()->format('Y-m-d H:i:s'));

        if ($message) {
            if ($throwable instanceof \Throwable) {
                $exceptionAttribute = [
                    'message' => $throwable->getMessage(),
                    'trace' => $throwable->getTraceAsString(),
                ];
            } else {
                $exceptionAttribute = ['message' => $throwable, 'trace' => ''];
            }

            if ($message instanceof GXRabbitMessage) {
                $messageId = $message->id;

                if ($messageDelivery = $message->deliveries->first()) {
                    if (count($exceptionAttribute) > 0) {
                        $responses = $messageDelivery->responses;
                        $responses[] = $exceptionAttribute;

                        $messageDelivery->responses = $responses;
                    }

                    $messageDelivery->statusId = GXRabbitMessageDeliveryStatus::ERROR_ID;
                    $messageDelivery->save();
                }
            } else {
                $messageId = $message;
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

    private function successConsuming($consumer, $queueMessage, $response = null)
    {
        $queueMessage->finished = true;
        $queueMessage->save();

        if ($messageDelivery = $queueMessage->deliveries->first()) {
            if ($response && (is_array($response) && count($response) > 0)) {
                $responses = $messageDelivery->responses;
                $responses[] = $response;

                $messageDelivery->responses = $responses;
            }

            $messageDelivery->statusId = GXRabbitMessageDeliveryStatus::FINISH_ID;
            $messageDelivery->save();
        }

        Log::info("RABBITMQ-SUCCESS: $consumer " . now()->format('Y-m-d H:i:s'));
    }

}

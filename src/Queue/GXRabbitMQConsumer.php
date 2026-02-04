<?php

namespace GlobalXtreme\RabbitMQ\Queue;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Constant\GXRabbitMessageDeliveryStatus;
use GlobalXtreme\RabbitMQ\Models\GXRabbitConnection;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessageDelivery;
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

    public function rabbitmqConsume(string $connectionType = GXRabbitConnectionType::GLOBAL)
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

    public function prepareManualConsume($messageId, $senderId)
    {
        if (!$messageId || !$senderId) {
            return null;
        }

        $serviceName = config('base.conf.service');

        $message = GXRabbitMessage::select('messages.*')
            ->leftJoin('message_deliveries', 'messages.id', '=', 'message_deliveries.messageId')
            ->where('messages.id', $messageId)
            ->where('messages.senderId', $senderId)
            ->where('message_deliveries.consumerService', $serviceName)
            ->with([
                'connection',
                'deliveries' => function ($query) use ($serviceName) {
                    $query->where('consumerService', $serviceName);
                }
            ])
            ->first();
        throw_if(!$message, new \Exception("Message not found"));

        $delivery = $message->deliveries->first();
        throw_if(!$delivery, new \Exception("Message delivery not found"));

        if ($delivery->statusId != GXRabbitMessageDeliveryStatus::ERROR_ID) {
            throw new \Exception("message delivery is not error");
        }

        return $message;
    }

    public function successConsuming($queueMessage, $response = null)
    {
        if (!$queueMessage->finished) {
            $queueMessage->finished = true;
            $queueMessage->save();
        }

        if ($messageDelivery = $queueMessage->deliveries->first()) {
            if ($response && (is_array($response) && count($response) > 0)) {
                $responses = $messageDelivery->responses;
                $responses[] = $response;

                $messageDelivery->responses = $responses;
            }

            $messageDelivery->statusId = GXRabbitMessageDeliveryStatus::FINISH_ID;
            $messageDelivery->save();

            $this->sendNotification($queueMessage, $messageDelivery, $response);
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

            $newConsumer = new $consumer($queueMessage, $data);
            $response = $newConsumer->consume();

            $this->successConsuming($queueMessage, $response);

            Log::info("RABBITMQ-SUCCESS: $consumer " . now()->format('Y-m-d H:i:s'));

        } catch (\Throwable $throwable) {
            if (!$queueMessage) {
                $queueMessage = $messageId;
            }

            $this->failedConsuming($consumer, $exchange, $queue, $queueMessage, $data, $throwable);
            Log::error($throwable);
        }
    }

    private function failedConsuming($consumer, $exchange, $queue, $message, $data, $throwable)
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

                    $this->sendNotification($message, $messageDelivery, $exceptionAttribute);
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

    private function sendNotification(GXRabbitMessage $message, $messageDelivery, $result)
    {
        if (!$messageDelivery->needNotification) {
            return;
        }

        if ($message->resend > 0 && $messageDelivery->statusId == GXRabbitMessageDeliveryStatus::ERROR_ID) {
            return;
        }

        $setExchangeQueueKey = function ($key): string {
            $keys = explode('.', $key);
            $lastKey = count($keys)-1;

            $keys[$lastKey] = "processed";
            $keys[$lastKey + 1] = "queue";

            return implode('.', $keys);
        };

        $queue = "";
        if ($message->exchange) {
            $queue = $setExchangeQueueKey($message->exchange);
        } elseif ($message->queue) {
            $queue = $setExchangeQueueKey($message->queue);
        }

        if ($queue) {
            $response = [
                'status' => GXRabbitMessageDeliveryStatus::idName($messageDelivery->statusId),
                'error' => null,
                'result' => null,
            ];

            if ($messageDelivery->statusId == GXRabbitMessageDeliveryStatus::FINISH_ID) {
                $response['result'] = $result;
            } else {
                $response['error'] = $result;
            }

            GXRabbitMQPublish::dispatch($response)
                ->onQueue($queue)
                ->onSender($message->senderId, $message->senderType);
        }
    }

}

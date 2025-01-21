<?php

namespace GlobalXtreme\RabbitMQ\Queue\Support;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Models\GXRabbitConnection;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class GXRabbitMQManager
{
    /**
     * @var string
     */
    protected string $connectionType = GXRabbitConnectionType::GLOBAL;

    /**
     * @var string
     */
    protected string $exchange = "";

    /**
     * @var string
     */
    protected string $queue = "";

    /**
     * @var float
     */
    protected float $connectionTimeout = 60;

    /**
     * @var array
     */
    protected array $payload = [];


    /**
     * @param string|array $message
     * @param int|null $queueMessageId
     */
    public function __construct(protected string|array $message, protected int|null $queueMessageId = null)
    {
    }


    /**
     * @param string $connection
     *
     * @return \GlobalXtreme\RabbitMQ\Queue\Support\GXRabbitMQManager
     */
    public function onConnectionType(string $connection): GXRabbitMQManager
    {
        $this->connectionType = $connection;

        return $this;
    }

    /**
     * @param string $exchange
     *
     * @return GXRabbitMQManager
     */
    public function onExchange(string $exchange): GXRabbitMQManager
    {
        $this->exchange = $exchange;

        return $this;
    }

    /**
     * @param string $queue
     *
     * @return GXRabbitMQManager
     */
    public function onQueue(string $queue): GXRabbitMQManager
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * The connection timeout to your host in seconds
     *
     * @param float $connectionTimeout
     *
     * @return GXRabbitMQManager
     */
    public function connectionTimeout(float $connectionTimeout = 60): GXRabbitMQManager
    {
        $this->connectionTimeout = $connectionTimeout;

        return $this;
    }


    /**
     * Handle the object's destruction.
     *
     * @throws \Throwable
     */
    public function __destruct()
    {
        try {

            if (!$this->connectionType) {
                $this->logError("Please set your connection first!");
                return;
            }

            if (!$this->exchange && !$this->queue) {
                $this->logError("Please set your exchange or queue first!");
                return;
            }

            $configuration = config("gx-rabbitmq.connection.types.$this->connectionType");
            if (!$configuration) {
                $this->logError("Your connection type does not exists!");
                return;
            }

            $rabbitConnection = $this->setGXRabbitConnection();
            if (!$rabbitConnection) {
                $this->logError("Your rabbitmq connection does not exists!");
                return;
            }

            $this->saveQueueMessage($rabbitConnection);

            $AMQPStreamConnection = new AMQPStreamConnection(
                $configuration['host'],
                $configuration['port'],
                $configuration['user'],
                $configuration['password'],
                $configuration['vhost'],
                connection_timeout: ($this->connectionTimeout ?: (config('gx-rabbitmq.timeout') ?: 60))
            );
            $channel = $AMQPStreamConnection->channel();

            $msg = new AMQPMessage(json_encode($this->payload));

            if ($this->exchange != "") {
                $channel->exchange_declare($this->exchange, 'fanout', false, true, false);
                $channel->basic_publish($msg, $this->exchange);
            } elseif ($this->queue != "") {
                $channel->queue_declare($this->queue, false, true, false, false);
                $channel->basic_publish($msg, '', $this->queue);
            }

            $channel->close();
            $AMQPStreamConnection->close();

        } catch (\Exception $exception) {
            Log::error($exception);
            return;
        }
    }


    /** --- SUB FUNCTIONS --- */

    private function setGXRabbitConnection()
    {
        return GXRabbitConnection::where('connection', $this->connectionType)
            ->where(function ($query) {
                if ($this->connectionType != GXRabbitConnectionType::GLOBAL) {
                    $query->where('service', config('base.conf.service'));
                }
            })
            ->first();
    }

    private function saveQueueMessage($connection)
    {
        $this->payload = [
            'data' => $this->message,
            'messageId' => $this->queueMessageId
        ];

        $queueMessage = null;
        if ($this->queueMessageId) {
            $queueMessage = GXRabbitMessage::find($this->queueMessageId);
        }

        if (!$queueMessage) {
            $queueMessage = GXRabbitMessage::create([
                'connectionId' => $connection->id,
                'exchange' => $this->exchange,
                'queue' => $this->queue,
                'senderId' => isset($this->message['id']) ? $this->message['id'] : null,
                'senderType' => isset($this->message['class']) ? $this->message['class'] : null,
                'senderService' => config('base.conf.service'),
                'payload' => $this->payload
            ]);
            if ($queueMessage) {
                $this->payload['messageId'] = $queueMessage->id;

                $queueMessage->payload = $this->payload;
                $queueMessage->save();
            }
        }

        return $queueMessage;
    }

    /**
     * @param string $message
     *
     * @return void
     */
    private function logError(string $message)
    {
        Log::error("RABBIT-PUBLISH: $message");
    }

}

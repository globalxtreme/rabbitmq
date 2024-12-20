<?php

namespace GlobalXtreme\RabbitMQ\Queue\Support;

use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;

class GXRabbitMQManager
{
    /**
     * @var string
     */
    protected string $connection = 'rabbitmq';

    /**
     * @var string
     */
    protected string $host = 'default';

    /**
     * @var string
     */
    protected string $exchange = 'direct';

    /**
     * @var array
     */
    protected array $queues = [];

    /**
     * @var string
     */
    protected string $key = '';

    /**
     * @var float
     */
    protected float $connectionTimeout = 60;

    /**
     * @var array
     */
    protected array $rabbitmqConf = [];

    /**
     * @var AMQPStreamConnection
     */
    protected $AMQPStreamConnection;

    /**
     * @var AMQPChannel
     */
    protected $AMQPChannel;

    /**
     * @var array
     */
    protected array $msgBody = [];

    /**
     * @var array
     */
    protected array $msgProperty = [];


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
     * @return GXRabbitMQManager
     */
    public function onConnection(string $connection): GXRabbitMQManager
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @param string $host
     *
     * @return GXRabbitMQManager
     */
    public function onHost(string $host): GXRabbitMQManager
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @param string $exchange
     * @param bool $ignoreExchangeName
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
        $this->queues[] = $queue;

        return $this;
    }

    /**
     * @param ...$queues
     *
     * @return GXRabbitMQManager
     */
    public function onMultiQueues(...$queues): GXRabbitMQManager
    {
        $this->queues = array_merge($this->queues, $queues);

        return $this;
    }

    /**
     * @param string $key
     *
     * @return GXRabbitMQManager
     */
    public function onKey(string $key): GXRabbitMQManager
    {
        $this->key = $key;

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

            if (!$this->connection) {
                $this->logError("Please set your connection first!");
                return;
            }

            $this->rabbitmqConf = config("queue.connections.$this->connection");
            if (!$this->rabbitmqConf) {
                $this->logError("Your connection invalid!");
                return;
            }

            if (!isset($this->rabbitmqConf['hosts'][$this->host]) || !$this->rabbitmqConf['hosts'][$this->host]) {
                $this->logError("Host [$this->host] not found. Please set your host in your configuration!");
                return;
            }

            if (!$this->exchange) {
                $this->logError("Please set your exchange first!");
                return;
            }

            // if (count($this->queues) == 0) {
            //     $this->logError("Please set your queue(s) first!");
            //     return;
            // }

            if (!isset($this->rabbitmqConf['exchanges'][$this->exchange])) {
                $this->logError("Exchange [$this->exchange] not found. Please set your exchange in your configuration!");
                return;
            }

            if (!$this->key) {
                $this->logError("Please set message key!");
                return;
            }

            $exchange = $this->rabbitmqConf['exchanges'][$this->exchange];

            $this->saveQueueMessage($exchange);

            $this->setAMQPChannel();

            $this->declareExchange($exchange);

            $this->publishMessage($exchange);

            $this->closeConnection();

        } catch (\Exception $exception) {
            Log::error($exception);
            return;
        }
    }


    /** --- SUB FUNCTIONS --- */

    private function saveQueueMessage(array $exchange)
    {
        $this->msgBody = [
            'key' => $this->key,
            'exchange' => $exchange['name'],
            'queue' => $this->rabbitmqConf['queue'],
            'message' => $this->message,
            'messageId' => $this->queueMessageId
        ];

        $queueMessage = null;
        if ($this->queueMessageId) {
            $queueMessage = GXRabbitMessage::find($this->queueMessageId);

            $this->msgBody['queue'] = $queueMessage?->queueSender;
        }

        $this->msgProperty = [
            'correlation_id' => Uuid::uuid4()->toString(),
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'content_type' => 'application/json'
        ];

        if (!$queueMessage) {
            $queueStatuses = [];
            foreach ($this->queues as $queue) {
                $queueStatuses[$queue] = false;
            }

            $payload = ['body' => $this->msgBody, 'properties' => $this->msgProperty];

            $queueMessage = GXRabbitMessage::create([
                'exchange' => $exchange['name'],
                'queueSender' => $this->rabbitmqConf['queue'],
                'queueConsumers' => $this->queues,
                'key' => $this->key,
                'senderId' => isset($this->message['id']) ? $this->message['id'] : null,
                'senderType' => isset($this->message['class']) ? $this->message['class'] : null,
                'statuses' => $queueStatuses,
                'payload' => $payload
            ]);
            if ($queueMessage) {
                $payload['body']['messageId'] = $queueMessage->id;
                $this->msgBody['messageId'] = $queueMessage->id;

                $queueMessage->payload = $payload;
                $queueMessage->save();
            }
        }

        return $queueMessage;
    }

    private function setAMQPChannel()
    {
        $host = $this->rabbitmqConf['hosts'][$this->host];
        $this->AMQPStreamConnection = new AMQPStreamConnection(
            $host['host'],
            $host['port'],
            $host['user'],
            $host['password'],
            $host['vhost'],
            connection_timeout: $this->connectionTimeout
        );

        $this->AMQPChannel = $this->AMQPStreamConnection->channel();
    }

    private function declareExchange(array $exchange)
    {
        $this->AMQPChannel->exchange_declare(
            $exchange['name'],
            $exchange['type'],
            $exchange['passive'],
            $exchange['durable'],
            $exchange['auto_delete']
        );
    }

    private function publishMessage(array $exchange)
    {
        $msg = new AMQPMessage(json_encode($this->msgBody), $this->msgProperty);

        if (count($this->queues) == 0) {
            $this->AMQPChannel->basic_publish($msg, $exchange['name']);
        }else{
            foreach ($this->queues as $queue) {
                $this->AMQPChannel->basic_publish($msg, $exchange['name'], $queue);
            }
        }
    }

    private function closeConnection()
    {
        $this->AMQPStreamConnection->close();
        $this->AMQPChannel->close();
    }

    /**
     * @param string $message
     *
     * @return void
     */
    private function logError(string $message)
    {
        Log::error("GX-RABBIT-QUEUE: $message");
    }

}

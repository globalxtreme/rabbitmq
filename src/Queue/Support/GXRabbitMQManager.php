<?php

namespace GlobalXtreme\RabbitMQ\Queue\Support;

use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessageFailed;
use GlobalXtreme\RabbitMQ\Models\GXRabbitQueue;
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
    protected $connection = 'rabbitmq';

    /**
     * @var string
     */
    protected $host = 'default';

    /**
     * @var string
     */
    protected $exchange = 'direct';

    /**
     * @var bool
     */
    protected $ignoreExchangeName = false;

    /**
     * @var array
     */
    protected $queues = [];

    /**
     * @var string
     */
    protected $key = '';

    /**
     * @var string
     */
    protected $consumeClass = 'GlobalXtreme\\RabbitMQ\\Jobs\\RabbitMQMessageJob';

    /**
     * @var float
     */
    protected $connectionTimeout = 60;

    /**
     * @var int|null
     */
    protected $failedId = null;

    /**
     * @var string|null
     */
    protected $failedKey = null;

    /**
     * @var bool|null
     */
    protected $repairStatus = null;

    /**
     * @var \Exception|null
     */
    protected $exception = null;

    /**
     * @var array
     */
    protected $rabbitmqConf = [];

    /**
     * @var AMQPStreamConnection
     */
    protected $AMQPStreamConnection;

    /**
     * @var AMQPChannel
     */
    protected $AMQPChannel;

    /**
     * @var array|string
     */
    protected $message;

    /**
     * @var int|null
     */
    protected $queueMessageId;


    /**
     * @param string|array $message
     * @param int|null $queueMessageId
     */
    public function __construct($message, $queueMessageId = null)
    {
        $this->message = $message;
        $this->queueMessageId = $queueMessageId;
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
    public function onExchange(string $exchange, bool $ignoreExchangeName = false): GXRabbitMQManager
    {
        $this->exchange = $exchange;
        $this->ignoreExchangeName = $ignoreExchangeName;

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
     * @param string $consumeClass
     *
     * @return GXRabbitMQManager
     */
    public function onConsumeClass(string $consumeClass): GXRabbitMQManager
    {
        $this->consumeClass = $consumeClass;

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
     * @param int|null $failedId
     *
     * @return GXRabbitMQManager
     */
    public function onFailedId($failedId): GXRabbitMQManager
    {
        $this->failedId = $failedId;

        return $this;
    }

    /**
     * @param string|null $failedKey
     * @param bool|null $repairStatus
     *
     * @return $this
     */
    public function onFailedKey($failedKey, $repairStatus = null): GXRabbitMQManager
    {
        $this->failedKey = $failedKey;
        $this->repairStatus = $repairStatus;

        return $this;
    }

    /**
     * @param \Exception|string|null $exception
     *
     * @return GXRabbitMQManager
     */
    public function onException($exception): GXRabbitMQManager
    {
        $this->exception = $exception;

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

            if (count($this->queues) == 0) {
                $this->onExchange('fanout', $this->ignoreExchangeName);
            }

            if ($this->ignoreExchangeName) {
                $exchange = collect($this->rabbitmqConf['exchanges'])->where('name', $this->exchange)->first();
            } else {
                $exchange = $this->rabbitmqConf['exchanges'][$this->exchange];
            }

            if (!$exchange || (is_array($exchange) && count($exchange) == 0)) {
                $this->logError("Exchange [$this->exchange] not found. Please set your exchange in your configuration!");
                return;
            }

            if (!$this->key) {
                $this->logError("Please set message key!");
                return;
            }

            DB::connection(config('gx-rabbitmq.db-connection'))->transaction(function () use ($exchange) {

                $queueMessage = $this->saveQueueMessage($exchange);

                $this->setAMQPChannel();

                $this->declareExchange($exchange);

                $this->publishMessage($queueMessage, $exchange);

                $this->closeConnection();

            });

        } catch (\Exception $exception) {
            Log::error($exception);
            return;
        }
    }


    /** --- SUB FUNCTIONS --- */

    private function saveQueueMessage($exchange)
    {
        $queueMessage = null;
        if ($this->queueMessageId) {
            $queueMessage = GXRabbitMessage::find($this->queueMessageId);
        }

        if ($this->failedId && !$queueMessage) {
            $queueFailed = GXRabbitMessageFailed::find($this->failedId);
            if ($queueFailed) {
                $queueMessage = $queueFailed->message;
            }
        }

        if (!$queueMessage) {
            $queueMessage = new GXRabbitMessage();

            $queueMessage->exchange = $exchange['name'];
            $queueMessage->queueSender = $this->rabbitmqConf['queue'];
            $queueMessage->key = $this->key;
            $queueMessage->senderId = isset($this->message['id']) ? $this->message['id'] : null;
            $queueMessage->senderType = isset($this->message['class']) ? $this->message['class'] : null;

            $queueMessage->save();
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
            false,
            'AMQPLAIN',
            null,
            'en_US',
            $this->connectionTimeout
        );

        $this->AMQPChannel = $this->AMQPStreamConnection->channel();
    }

    private function declareExchange($exchange)
    {
        $this->AMQPChannel->exchange_declare(
            $exchange['name'],
            $exchange['type'],
            $exchange['passive'],
            $exchange['durable'],
            $exchange['auto_delete']
        );
    }

    private function publishMessage($queueMessage, $exchange)
    {
        $exceptionMessage = [];
        if ($this->failedId || $this->exception) {

            $exception = null;
            if ($this->exception) {
                if ($this->exception instanceof \Exception) {
                    $exception = [
                        'message' => $this->exception->getMessage(),
                        'trace' => $this->exception->getTraceAsString(),
                    ];
                } else {
                    $exception = ['message' => $this->exception, 'trace' => ''];
                }
            }

            $exceptionMessage = [
                'failedId' => $this->failedId,
                'success' => ($this->repairStatus !== null) ? $this->repairStatus : null,
                'exception' => $exception,
            ];
        }

        $body = json_encode([
            'uuid' => Uuid::uuid4()->toString(),
            'displayName' => $this->consumeClass,
            'job' => "Illuminate\\Queue\\CallQueuedHandler@call",
            'data' => [
                'commandName' => $this->consumeClass,
                'command' => serialize(new $this->consumeClass([
                        'key' => $this->key,
                        'failedKey' => $this->failedKey,
                        'exchange' => $exchange['name'],
                        'queue' => $this->rabbitmqConf['queue'],
                        'messageId' => $queueMessage->id,
                        'message' => $this->message
                    ] + $exceptionMessage))
            ]
        ]);

        $properties = [
            'correlation_id' => Uuid::uuid4()->toString(),
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'content_type' => 'application/json'
        ];

        if (!$queueMessage->payload) {
            $queueMessage->update(['payload' => ['body' => json_decode($body, true), 'properties' => $properties]]);
        }

        if (!$queueMessage->queueConsumers) {
            if (count($this->queues) == 0) {
                $queues = GXRabbitQueue::select('name')->get()->pluck('name')->toArray();
            } else {
                $queues = $this->queues;
            }

            $queueMessage->update(['queueConsumers' => $queues]);
        }

        $msg = new AMQPMessage($body, $properties);

        if (count($this->queues) == 0) {
            $this->AMQPChannel->basic_publish($msg, $exchange['name']);
        } else {
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

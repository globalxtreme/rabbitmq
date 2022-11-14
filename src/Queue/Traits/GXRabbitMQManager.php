<?php

namespace GlobalXtreme\RabbitMQ\Queue\Traits;

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
    protected string $exchange = 'default';

    /**
     * @var bool
     */
    protected bool $ignoreExchangeName = false;

    /**
     * @var array
     */
    protected array $queues = [];

    /**
     * @var string
     */
    protected string $key = '';

    /**
     * @var string
     */
    protected string $consumeClass = 'App\\Jobs\\RabbitMQMessageJob';

    /**
     * @var float
     */
    protected float $connectionTimeout = 60;

    /**
     * @var int|null
     */
    protected int|null $failedId = null;

    /**
     * @var string|null
     */
    protected string|null $failedKey = null;

    /**
     * @var bool|null
     */
    protected bool|null $repairStatus = null;

    /**
     * @var \Exception|null
     */
    protected \Exception|null $exception = null;

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
     * @param string|array $message
     */
    public function __construct(protected string|array $message)
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
    public function onFailedId(int|null $failedId): GXRabbitMQManager
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
    public function onFailedKey(string|null $failedKey, bool|null $repairStatus = null): GXRabbitMQManager
    {
        $this->failedKey = $failedKey;
        $this->repairStatus = $repairStatus;

        return $this;
    }

    /**
     * @param \Exception|null $exception
     *
     * @return GXRabbitMQManager
     */
    public function onException(\Exception|null $exception): GXRabbitMQManager
    {
        $this->exception = $exception;

        return $this;
    }


    /**
     * Handle the object's destruction.
     *
     * @return void
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

    private function publishMessage($exchange)
    {
        $exceptionMessage = [];
        if ($this->failedId || $this->exception) {
            $exceptionMessage = [
                'failedId' => $this->failedId,
                'success' => ($this->repairStatus !== null) ? $this->repairStatus : null,
                'exception' => $this->exception ? [
                    'message' => $this->exception->getMessage(),
                    'trace' => $this->exception->getTraceAsString(),
                ] : null,
            ];
        }

        $body = json_encode([
            "uuid" => Uuid::uuid4()->toString(),
            "displayName" => $this->consumeClass,
            'job' => "Illuminate\\Queue\\CallQueuedHandler@call",
            "data" => [
                'commandName' => $this->consumeClass,
                'command' => serialize(new $this->consumeClass([
                        'key' => $this->key,
                        'failedKey' => $this->failedKey,
                        'exchange' => $exchange['name'],
                        'queue' => $this->rabbitmqConf['queue'],
                        'message' => $this->message
                    ] + $exceptionMessage))
            ]
        ]);

        $properties = [
            'correlation_id' => Uuid::uuid4()->toString(),
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'content_type' => 'application/json'
        ];

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

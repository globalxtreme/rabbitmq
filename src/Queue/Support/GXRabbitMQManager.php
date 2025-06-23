<?php

namespace GlobalXtreme\RabbitMQ\Queue\Support;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Constant\GXRabbitMessageDeliveryStatus;
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
     * @var AMQPStreamConnection|null
     */
    protected AMQPStreamConnection|null $AMQPStreamConnection = null;

    /**
     * @var GXRabbitConnection|null
     */
    protected GXRabbitConnection|null $GXRabbitConnection = null;

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
    protected array $payload = [], $deliveries = [];

    /**
     * @var bool
     */
    private bool $isError = false;


    /**
     * @param string|array $message
     * @param GXRabbitMessage|int|null $queueMessage
     */
    public function __construct(protected string|array $message, protected GXRabbitMessage|int|null $queueMessage = null)
    {
    }


    /**
     * @param GXRabbitConnection|string $GXRabbitConnection
     *
     * @return \GlobalXtreme\RabbitMQ\Queue\Support\GXRabbitMQManager
     */
    public function onConnection(GXRabbitConnection|string $GXRabbitConnection): GXRabbitMQManager
    {
        if ($GXRabbitConnection instanceof GXRabbitConnection) {
            $this->connectionType = $GXRabbitConnection->connection;
            $this->GXRabbitConnection = $GXRabbitConnection;

            $this->setAMQPStreamConnection(
                $GXRabbitConnection->IP,
                $GXRabbitConnection->port,
                $GXRabbitConnection->username,
                $GXRabbitConnection->password,
            );
        } else {
            $this->connectionType = $GXRabbitConnection;

            $this->setGXRabbitConnection();
            $this->setAMQPStreamConnection(
                $this->GXRabbitConnection->IP,
                $this->GXRabbitConnection->port,
                $this->GXRabbitConnection->username,
                $this->GXRabbitConnection->password,
            );
        }

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
     * @param array $deliveries
     *
     * @return GXRabbitMQManager
     */
    public function onDeliveries(array $deliveries): GXRabbitMQManager
    {
        $this->deliveries = $deliveries;

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

            if ($this->isError) {
                return;
            }

            if (!$this->connectionType) {
                $this->logError("Please set your connection first!");
            }

            if (!$this->exchange && !$this->queue) {
                $this->logError("Please set your exchange or queue first!");
            }

            if (!$this->GXRabbitConnection) {
                $this->setGXRabbitConnection();
            }

            $this->saveQueueMessage();

            if (!$this->AMQPStreamConnection) {
                $configuration = config("gx-rabbitmq.connection.types.$this->connectionType");
                if (!$configuration) {
                    $this->logError("Your connection type does not exists!");
                }

                $this->setAMQPStreamConnection(
                    $configuration['host'],
                    $configuration['port'],
                    $configuration['user'],
                    $configuration['password'],
                    $configuration['vhost']
                );
            }

            $channel = $this->AMQPStreamConnection->channel();

            $msg = new AMQPMessage(json_encode($this->payload));

            if ($this->exchange != "") {
                $channel->exchange_declare($this->exchange, 'fanout', false, true, false);
                $channel->basic_publish($msg, $this->exchange);
            } elseif ($this->queue != "") {
                $channel->queue_declare($this->queue, false, true, false, false);
                $channel->basic_publish($msg, '', $this->queue);
            }

            $channel->close();
            $this->AMQPStreamConnection->close();

        } catch (\Exception $exception) {
            Log::error($exception);
            return;
        }
    }


    /** --- SUB FUNCTIONS --- */

    private function setGXRabbitConnection()
    {
        $this->GXRabbitConnection = GXRabbitConnection::where('connection', $this->connectionType)
            ->where(function ($query) {
                if ($this->connectionType != GXRabbitConnectionType::GLOBAL) {
                    $query->where('service', config('base.conf.service'));
                }
            })
            ->first();
        if (!$this->GXRabbitConnection) {
            $this->logError("Your rabbitmq connection does not exists!");
        }
    }

    private function setAMQPStreamConnection($host,
                                             $port,
                                             $user,
                                             $password,
                                             $vhost = '/')
    {
        try {
            $this->AMQPStreamConnection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost,
                connection_timeout: ($this->connectionTimeout ?: (config('gx-rabbitmq.timeout') ?: 60))
            );
        } catch (\Exception $exception) {
            $this->logError($exception->getMessage());
        }
    }

    private function saveQueueMessage()
    {
        $this->payload = [
            'data' => $this->message,
            'messageId' => null
        ];

        if ($this->queueMessage) {
            if (is_int($this->queueMessage)) {
                $this->queueMessage = GXRabbitMessage::find($this->queueMessage);
                if (!$this->queueMessage) {
                    $this->logError("Your message doesn't exists!");
                }
            }

            $this->payload['messageId'] = $this->queueMessage->id;
        } else {
            $this->queueMessage = GXRabbitMessage::create([
                'connectionId' => $this->GXRabbitConnection->id,
                'exchange' => $this->exchange,
                'queue' => $this->queue,
                'finished' => (!$this->queue && $this->exchange),
                'senderId' => isset($this->message['id']) ? $this->message['id'] : null,
                'senderType' => isset($this->message['class']) ? $this->message['class'] : null,
                'senderService' => config('base.conf.service'),
                'payload' => $this->payload
            ]);
            if ($this->queueMessage) {
                $this->payload['messageId'] = $this->queueMessage->id;

                $this->queueMessage->payload = $this->payload;
                $this->queueMessage->save();

                if (count($this->deliveries) > 0) {
                    foreach ($this->deliveries as $delivery) {
                        $this->queueMessage->deliveries()->updateOrCreate(
                            ['consumerService' => $delivery],
                            ['statusId' => GXRabbitMessageDeliveryStatus::PENDING_ID]
                        );
                    }
                }
            }
        }
    }

    /**
     * @param string $message
     *
     * @return mixed
     * @throws \Exception
     */
    private function logError(string $message)
    {
        Log::error("RABBIT-PUBLISH: $message");
        $this->isError = true;

        throw new \Exception($message);
    }

}

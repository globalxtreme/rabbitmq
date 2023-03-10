<?php

namespace GlobalXtreme\RabbitMQ\Command;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitKeyConstant;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessageFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'gx-rabbitmq:consume')]
class GXRabbitMQConsumeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'gx-rabbitmq:consume';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'gx-rabbitmq:consume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start processing jobs on the queue as a daemon';

    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * @var array
     */
    protected array $configuration = [];

    /**
     * @var array
     */
    protected array $body = [];

    /**
     * @var GXRabbitMessage
     */
    protected GXRabbitMessage $queueMessage;


    public function handle()
    {
        try {
            $this->setConfiguration();

            $this->setChannel();

            $this->line("\n<bg=blue>[GX-Info]</> Processing jobs from the <options=bold>[{$this->configuration['queue']}]</> queue.\n");

            $this->consume();

            while ($this->channel->is_open()) {
                $this->channel->wait();
            }

        } catch (\Exception $exception) {
            $this->logError($exception);
        }
    }


    /** --- SUB FUNCTIONS --- */

    private function setConfiguration()
    {
        $this->configuration = config('queue.connections.rabbitmq');
    }

    private function setChannel()
    {
        $host = $this->configuration['hosts']['default'];

        $connection = new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password']);
        $this->channel = $connection->channel();

        $this->channel->queue_declare($this->configuration['queue'], false, true, false, false);
    }

    private function consume()
    {
        $callback = function ($message) {
            $this->processMessage($message);
        };

        $this->channel->basic_consume($this->configuration['queue'], '', false, true, false, false, $callback);
    }

    private function processMessage($message)
    {
        $this->warn('CONSUMING:.................................... ' . now()->format('Y-m-d H:i:s'));

        try {

            $this->body = json_decode($message->body, true);

            $this->queueMessage = GXRabbitMessage::find($this->body['messageId']);

            if (!isset($this->body['key']) || !$this->body['key']) {
                $this->logError("Your key [{$this->body['key']}] invalid!");
                return;
            }

            $key = $this->body['key'];
            $service = GXRabbitKeyConstant::callMessageClass($this->queueMessage, $key);
            if (!$service) {
                $this->logError("Message broker key does not exists or not yet set service class! [$key]");
                return;
            }

            $service->handle($this->body['message']);

            $this->updateMessageStatus();

            $this->info('SUCCESS:...................................... ' . now()->format('Y-m-d H:i:s'));
        } catch (\Exception $exception) {
            $this->logError($exception->getMessage());
        }
    }

    private function logError(\Exception|string $exception)
    {
        Log::error("RabbitMQ-Consume: " . ($exception instanceof \Exception ? $exception->getMessage() : $exception));

        $this->saveMessageFailed($exception);

        $this->error('FAILED:....................................... ' . now()->format('Y-m-d H:i:s'));
    }

    private function saveMessageFailed(\Exception|string|null $exception)
    {
        if ($exception instanceof \Exception) {
            $exceptionAttribute = [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ];
        } else {
            $exceptionAttribute = ['message' => $exception, 'trace' => ''];
        }

        GXRabbitMessageFailed::create([
            'messageId' => $this->body['messageId'],
            'sender' => $this->queueMessage->queueSender,
            'consumer' => $this->configuration['queue'],
            'key' => $this->body['key'],
            'payload' => $this->body['message'],
            'exception' => $exceptionAttribute,
        ]);
    }

    private function updateMessageStatus()
    {
        $statuses = $this->queueMessage->statuses;
        $statuses[$this->configuration['queue']] = true;

        $finished = true;
        foreach ($statuses as $status) {
            if (!$status) {
                Log::info('testing -success');
                $finished = false;
                break;
            }
        }

        $this->queueMessage->finished = $finished;
        $this->queueMessage->statuses = $statuses;
        $this->queueMessage->save();
    }

}

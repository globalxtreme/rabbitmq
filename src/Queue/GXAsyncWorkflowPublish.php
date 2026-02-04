<?php

namespace GlobalXtreme\RabbitMQ\Queue;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitAsyncWorkflowStatus;
use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Form\GXAsyncWorkflowForm;
use GlobalXtreme\RabbitMQ\Models\GXRabbitConfiguration;
use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflow;
use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflowStep;
use GlobalXtreme\RabbitMQ\Models\GXRabbitConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class GXAsyncWorkflowPublish
{
    /**
     * @var string
     */
    protected string $referenceId = "";

    /**
     * @var string|null
     */
    protected string|null $createdBy = null, $createdByName = null;

    /**
     * @var string|null
     */
    protected string|null $description = null, $successMessage = null, $errorMessage = null;

    /**
     * @var bool
     */
    protected bool $isError = false;

    /**
     * @var int
     */
    protected int $totalStep = 0, $connectionTimeout = 60;

    /**
     * @var GXAsyncWorkflowForm|null
     */
    protected GXAsyncWorkflowForm|null $firstStep = null;

    /**
     * @var array
     */
    protected array $steps = [];

    /**
     * @var GXRabbitConnection|null
     */
    protected GXRabbitConnection|null $connection = null;

    /**
     * @var AMQPStreamConnection|null
     */
    protected AMQPStreamConnection|null $AMQPStreamConnection = null;


    /**
     * @param string|null $action
     * @param Model|int|string|null $reference
     * @param string|null $referenceType
     * @param bool $isStrict
     *
     * @throws \Exception
     */
    public function __construct(
        protected string|null           $action = null,
        protected Model|int|string|null $reference = null,
        protected string|null           $referenceType = null,
        protected bool                  $isStrict = false,
    )
    {
        $this->onReference($this->reference, $this->referenceType);
    }


    /**
     * @param GXAsyncWorkflowForm $step
     *
     * @return GXAsyncWorkflowPublish
     */
    public function onStep(GXAsyncWorkflowForm $step): GXAsyncWorkflowPublish
    {
        $this->totalStep++;

        $step->stepOrder = $this->totalStep;

        if (!$step->queue || $step->queue == "") {
            $step->queue = sprintf("%s.%s.async-workflow", $step->service, $this->action);
        }

        $this->steps[] = $step;

        if ($this->totalStep == 1) {
            $this->firstStep = $step;
        }

        return $this;
    }

    /**
     * @param Model|int|string|null $reference
     * @param string|null $referenceType
     *
     * @return GXAsyncWorkflowPublish
     * @throws \Exception
     */
    public function onReference(Model|int|string|null $reference, string|null $referenceType = null): GXAsyncWorkflowPublish
    {
        if ($reference instanceof Model) {
            $this->referenceId = $reference->id;
            $this->referenceType = $reference::class;
        } elseif (is_int($reference) || is_string($reference)) {
            if (!$referenceType || $referenceType == "") {
                $this->logError("Please set reference type");
            }

            $this->referenceId = $reference;
            $this->referenceType = $referenceType;
        }

        return $this;
    }

    /**
     * @param string $createdBy
     * @param string $createdByName
     *
     * @return GXAsyncWorkflowPublish
     */
    public function setCreatedBy(string $createdBy, string $createdByName): GXAsyncWorkflowPublish
    {
        $this->createdBy = $createdBy;
        $this->createdByName = $createdByName;

        return $this;
    }

    /**
     * @param string $description
     *
     * @return GXAsyncWorkflowPublish
     */
    public function setDescription(string $description): GXAsyncWorkflowPublish
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param string $message
     *
     * @return GXAsyncWorkflowPublish
     */
    public function setSuccessMessage(string $message): GXAsyncWorkflowPublish
    {
        $this->successMessage = $message;

        return $this;
    }

    /**
     * @param string $message
     *
     * @return GXAsyncWorkflowPublish
     */
    public function setErrorMessage(string $message): GXAsyncWorkflowPublish
    {
        $this->errorMessage = $message;

        return $this;
    }

    /**
     * @return GXAsyncWorkflowPublish
     */
    public function isStrict(): GXAsyncWorkflowPublish
    {
        $this->isStrict = true;

        return $this;
    }

    /**
     * @param float $connectionTimeout
     *
     * @return GXAsyncWorkflowPublish
     */
    public function connectionTimeout(float $connectionTimeout = 60): GXAsyncWorkflowPublish
    {
        $this->connectionTimeout = $connectionTimeout;

        return $this;
    }


    public function push()
    {
        try {

            $serviceName = config('base.conf.service');

            if (!$this->action) {
                $this->logError("Please set your action");
            }

            if (!$this->referenceId || !$this->referenceType) {
                $this->logError("Please set your reference");
            }

            if (count($this->steps) == 0) {
                $this->logError("Please setup your workflow step");
            }

            if (!$this->firstStep->payload) {
                $this->logError("Please setup your payload to first step order");
            }

            if ($this->isStrict) {
                $totalWorkflow = GXRabbitAsyncWorkflow::where('action', $this->action)
                    ->where('referenceId', $this->referenceId)
                    ->where('referenceType', $this->referenceType)
                    ->where('referenceService', $serviceName)
                    ->where('statusId', '!=', GXRabbitAsyncWorkflowStatus::SUCCESS_ID)
                    ->count();
                if ($totalWorkflow > 0) {
                    $this->logError("You have an asynchronous workflow not yet finished. Please check your workflow status and reprocess");
                }
            }

            $this->setConnection();

            $workflow = GXRabbitAsyncWorkflow::create([
                'action' => $this->action,
                'description' => $this->description,
                'referenceId' => $this->referenceId,
                'referenceType' => $this->referenceType,
                'statusId' => GXRabbitAsyncWorkflowStatus::PENDING_ID,
                'referenceService' => $serviceName,
                'successMessage' => $this->successMessage,
                'errorMessage' => $this->errorMessage,
                'totalStep' => $this->totalStep,
                'allowResendAt' => GXRabbitConfiguration::setAllowResendAt(),
                'createdBy' => $this->createdBy,
                'createdByName' => $this->createdByName,
            ]);
            if (!$workflow) {
                $this->logError("Unable to create async workflow");
            }

            foreach ($this->steps as $step) {
                $stepForm = [
                    'service' => $step->service,
                    'queue' => $step->queue,
                    'stepOrder' => $step->stepOrder,
                    'statusId' => GXRabbitAsyncWorkflowStatus::PENDING_ID,
                    'description' => $step->description,
                    'payload' => $step->payload ?: null,
                ];

                $payload = $step->payload ?: null;
                if ($payload != null) {
                    if ($step->stepOrder == 1) {
                        $stepForm['payload'] = $payload;
                    } else {
                        $stepForm['forwardPayload'] = [$this->action => $payload];
                    }
                }

                $workflow->steps()->create($stepForm);
            }

            $this->sendToMonitoringEvent($workflow);

            $this->pushWorkflowMessage($workflow->id, $this->firstStep->stepOrder, $this->firstStep->queue, $this->firstStep->payload);

        } catch (\Throwable $throw) {
            $this->logError($throw->getMessage());
        }
    }

    public function pushWorkflowMessage($workflowId, $stepOrder, string $queue, array $payload)
    {
        try {

            if (!$this->connection) {
                $this->setConnection();
            }

            $channel = $this->AMQPStreamConnection->channel();

            $msg = new AMQPMessage(json_encode([
                'data' => $payload,
                'workflowId' => $workflowId,
                'stepOrder' => $stepOrder,
            ]));

            if ($queue != "") {
                $channel->queue_declare($queue, false, true, false, false);
                $channel->basic_publish($msg, '', $queue);
            }

            $channel->close();
            $this->AMQPStreamConnection->close();

        } catch (\Throwable $throw) {
            $this->logError($throw->getMessage());
        }
    }


    /** --- SUB FUNCTIONS --- */

    private function setConnection()
    {
        $configuration = config("gx-rabbitmq.connection.types." . GXRabbitConnectionType::GLOBAL);
        if (!$configuration) {
            $this->logError("Your connection type does not exists!");
        }

        $this->connection = GXRabbitConnection::where('connection', GXRabbitConnectionType::GLOBAL)->first();
        if (!$this->connection) {
            $this->logError("Your rabbitmq connection does not exists!");
        }

        try {
            $this->AMQPStreamConnection = new AMQPStreamConnection(
                $configuration['host'],
                $configuration['port'],
                $configuration['user'],
                $configuration['password'],
                $configuration['vhost'],
                connection_timeout: ($this->connectionTimeout ?: (config('gx-rabbitmq.timeout') ?: 60))
            );
        } catch (\Throwable $throw) {
            $this->logError($throw->getMessage());
        }
    }

    private function sendToMonitoringEvent($workflow)
    {
        $result = [
            'id' => $workflow->id,
            'service' => $workflow->referenceService,
            'createdBy' => $workflow->createdBy,
        ];

        $channel = "ws-channel.async-workflow.monitoring:asa.monitoring.list";

        $client = Redis::connection('async-workflow')->client();
        $client->connect(env('REDIS_ASYNC_WORKFLOW_HOST'), env('REDIS_ASYNC_WORKFLOW_PORT'));
        $client->publish($channel, json_encode([
            "event" => "monitoring",
            "error" => "",
            "result" => $result,
        ]));
    }

    /**
     * @param string $message
     *
     * @return mixed
     * @throws \Exception
     */
    private function logError(string $message)
    {
        Log::error("ASYNC-WORKFLOW: $message");
        $this->isError = true;

        throw new \Exception($message);
    }

}

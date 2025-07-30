<?php

namespace GlobalXtreme\RabbitMQ\Queue;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitAsyncWorkflowStatus;
use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Constant\GXRabbitMessageDeliveryStatus;
use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflow;
use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflowStep;
use GlobalXtreme\RabbitMQ\Queue\Contract\GXAsyncWorkflowForwardPayload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class GXAsyncWorkflowConsumer
{
    /**
     * @var array
     */
    protected $queues = [];


    /** --- SETTER --- */

    public function setQueues(array $queues)
    {
        foreach ($queues as $queue => $consumer) {
            $this->queues[$queue] = $consumer;
        }
    }


    /** --- MAIN FUNCTIONS --- */

    public function consume()
    {
        $connectionType = GXRabbitConnectionType::GLOBAL;
        $configuration = config("gx-rabbitmq.connection.types.$connectionType");

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

        foreach ($this->queues ?: [] as $queue => $consumer) {
            $channel->queue_declare($queue, false, true, false, false);

            $channel->basic_qos(0, 1, false);

            $channel->basic_consume($queue, '', false, false, false, false, function ($msg) use ($channel, $queue, $consumer) {
                $this->processMessage($queue, $consumer, $msg);
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


    /** --- SUB FUNCTIONS --- */

    private function processMessage($queue, $consumer, $message)
    {
        Log::info("RABBITMQ-CONSUMING: $consumer " . now()->format('Y-m-d H:i:s'));

        $workflow = null;
        $workflowStep = null;

        try {

            $body = json_decode($message->getBody(), true);
            $data = $body['data'];

            $serviceName = config('base.conf.service');
            $workflow = GXRabbitAsyncWorkflow::with([
                'steps' => function ($query) use ($queue, $serviceName) {
                    $query->where('queue', $queue);
                }
            ])->find($body['workflowId']);
            if (!$workflow) {
                $this->failedConsuming($consumer, null, null, "Async workflow Not found [{$body['workflowId']}]");
                return;
            }

            if ($workflow->statusId == GXRabbitAsyncWorkflowStatus::SUCCESS_ID) {
                $this->failedConsuming($consumer, $workflow, null, "Your async workflow already finished [{$body['workflowId']}]");
                return;
            }

            $workflowStep = $workflow->steps->where('queue', $queue)->first();
            if (!$workflowStep) {
                $this->failedConsuming($consumer, $workflow, null, "Async workflow step Not found [{$body['workflowId']}]");
                return;
            }

            $this->startProcessing($workflow, $workflowStep);

            $consumerClass = new $consumer($workflowStep, $data);

            $nextWorkflowStep = null;
            if ($workflowStep->statusId == GXRabbitAsyncWorkflowStatus::SUCCESS_ID) {
                $nextWorkflowStep = $workflow->steps()->where('stepOrder', '>', $workflowStep->stepOrder)
                    ->orderBy('stepOrder', 'ASC')
                    ->first();
                if (!$nextWorkflowStep || $nextWorkflowStep->statusId == GXRabbitAsyncWorkflowStatus::SUCCESS_ID) {
                    $this->failedConsuming($consumer, $workflow, $workflowStep, "Your all async workflow step already finished [{$body['workflowId']}]");
                    return;
                }

                $response = $consumerClass->response();
            } else {
                $response = $consumerClass->consume();
            }

            $forwardPayload = [];
            if ($consumerClass instanceof GXAsyncWorkflowForwardPayload) {
                $forwardPayload = $consumerClass->forwardPayload();
            }

            $this->successConsuming($workflow, $workflowStep, $nextWorkflowStep, $response, $forwardPayload);

            Log::info("RABBITMQ-SUCCESS: $consumer " . now()->format('Y-m-d H:i:s'));

        } catch (\Throwable $throwable) {
            $this->failedConsuming($consumer, $workflow, $workflowStep, $throwable);
            Log::error($throwable);
        }
    }

    private function startProcessing($workflow, $workflowStep)
    {
        if ($workflowStep->statusId != GXRabbitAsyncWorkflowStatus::PROCESSING_ID && $workflowStep->statusId != GXRabbitAsyncWorkflowStatus::SUCCESS_ID) {
            $workflowStep->statusId = GXRabbitAsyncWorkflowStatus::PROCESSING_ID;
            $workflowStep->save();
        }

        if ($workflow->statusId != GXRabbitAsyncWorkflowStatus::PROCESSING_ID && $workflow->statusId != GXRabbitAsyncWorkflowStatus::SUCCESS_ID) {
            $workflow->statusId = GXRabbitAsyncWorkflowStatus::PROCESSING_ID;
            $workflow->save();
        }

        $this->sendToMonitoringEvent($workflow, $workflowStep);
    }

    private function failedConsuming($consumer, $workflow, $workflowStep, $throwable)
    {
        Log::error("RABBITMQ-FAILED: $consumer " . now()->format('Y-m-d H:i:s'));

        if ($workflow) {
            if ($throwable instanceof \Throwable) {
                $exceptionAttribute = [
                    'message' => $throwable->getMessage(),
                    'trace' => $throwable->getTraceAsString(),
                ];
            } else {
                $exceptionAttribute = ['message' => $throwable, 'trace' => ''];
            }

            if ($workflow instanceof GXRabbitAsyncWorkflow) {
                $errors = $workflow->errors ?: [];
                $errors[] = $exceptionAttribute;

                $workflow->errors = $errors;
                $workflow->statusId = GXRabbitAsyncWorkflowStatus::ERROR_ID;
                $workflow->save();
            }

            if ($workflowStep) {
                if ($workflowStep instanceof GXRabbitAsyncWorkflowStep) {
                    $errors = $workflowStep->errors ?: [];
                    $errors[] = $exceptionAttribute;

                    $workflowStep->errors = $errors;
                    $workflowStep->statusId = GXRabbitAsyncWorkflowStatus::ERROR_ID;
                    $workflowStep->save();
                }
            }

            $this->sendNotification($workflow, $workflowStep, $exceptionAttribute['message']);
            $this->sendToMonitoringEvent($workflow, $workflowStep);
        }
    }

    private function successConsuming($workflow, $workflowStep, $nextWorkflowStep, $response = null, $forwardPayloads = [])
    {
        $forwardSteps = $workflow->steps()->where('stepOrder', '>', $workflowStep->stepOrder)
            ->whereIn('queue', array_keys($forwardPayloads))
            ->get();
        foreach ($forwardSteps ?: [] as $forwardStep) {
            $forwardPayload = $forwardStep->forwardPayload ?: [];

            $originStepPayload = [];
            if (!empty($forwardPayload[$workflowStep->queue])) {
                $originStepPayload = $forwardPayload[$workflowStep->queue];
            }

            $this->remappingForwardPayload($forwardPayloads[$forwardStep->queue], $originStepPayload);

            $forwardPayload[$workflowStep->queue] = $originStepPayload;

            $forwardStep->forwardPayload = $forwardPayload;
            $forwardStep->save();
        }

        if (!$nextWorkflowStep) {
            $nextWorkflowStep = $workflow->steps()->where('stepOrder', '>', $workflowStep->stepOrder)
                ->orderBy('stepOrder', 'ASC')
                ->first();
        }

        if (!$nextWorkflowStep && $workflow->statusId != GXRabbitAsyncWorkflowStatus::SUCCESS_ID) {
            $workflow->statusId = GXRabbitAsyncWorkflowStatus::SUCCESS_ID;
            $workflow->save();
        }

        if ($workflowStep->statusId != GXRabbitAsyncWorkflowStatus::SUCCESS_ID) {
            $workflowStep->response = $response;
            $workflowStep->statusId = GXRabbitAsyncWorkflowStatus::SUCCESS_ID;
            $workflowStep->save();
        }

        if ($nextWorkflowStep) {
            $nextWorkflowStep->payload = $response;
            $nextWorkflowStep->save();

            if ($nextWorkflowStep->statusId != GXRabbitAsyncWorkflowStatus::SUCCESS_ID) {
                $payload = $response ?: [];
                if ($nextWorkflowStep->forwardPayload && count($nextWorkflowStep->forwardPayload ?: []) > 0) {
                    foreach ($nextWorkflowStep->forwardPayload as $fKey => $forwardPayload) {
                        $this->mergeForwardPayloadToPayload($forwardPayload, $payload);
                    }
                }

                if (count($payload) > 0) {
                    $publish = new GXAsyncWorkflowPublish();
                    $publish->pushWorkflowMessage($workflow->id, $nextWorkflowStep->queue, $payload);
                }
            }
        }

        if ($workflow->statusId == GXRabbitAsyncWorkflowStatus::SUCCESS_ID) {
            $this->sendNotification($workflow, $workflowStep, $workflow->successMessage);
        }

        $this->sendToMonitoringEvent($workflow, $workflowStep);
    }

    private function sendNotification($workflow, $workflowStep, $message)
    {
        // Tunggu business
    }

    private function sendToMonitoringEvent($workflow, $workflowStep)
    {
        $result = [
            'id' => $workflow->id,
            'action' => $workflow->action,
            'status' => GXRabbitAsyncWorkflowStatus::idName($workflow->statusId),
            'totalStep' => $workflow->totalStep,
            'reprocessed' => $workflow->reprocessed,
            'createdBy' => $workflow->createdByName,
            'createdAt' => optional($workflow->createdAt)->format('d/m/Y H:i:s'),
            'reference' => [
                'id' => $workflow->referenceId,
                'type' => $workflow->referenceType,
                'service' => $workflow->referenceService,
            ],
            'step' => null
        ];

        if ($workflowStep) {
            $result['step'] = [
                'id' => $workflowStep->id,
                'service' => $workflowStep->service,
                'queue' => $workflowStep->queue,
                'stepOrder' => $workflowStep->stepOrder,
                'status' => GXRabbitAsyncWorkflowStatus::idName($workflowStep->statusId),
                'description' => $workflowStep->description,
                'payload' => $workflowStep->payload,
                'forwardPayload' => $workflowStep->forwardPayload,
                'errors' => $workflowStep->errors,
                'response' => $workflowStep->response,
                'reprocessed' => $workflowStep->reprocessed,
                'createdAt' => optional($workflow->createdAt)->format('d/m/Y H:i:s'),
                'updatedAt' => optional($workflow->updatedAt)->format('d/m/Y H:i:s'),
            ];
        }

        $channel = "ws-channel.async-workflow.monitoring";
        $channel .= ":$workflow->action-$workflow->referenceId";

        $client = Redis::connection('async-workflow')->client();
        $client->connect(env('REDIS_ASYNC_WORKFLOW_HOST'), env('REDIS_ASYNC_WORKFLOW_PORT'));
        $client->publish($channel, json_encode([
            "event" => "monitoring",
            "error" => "",
            "result" => $result,
        ]));
    }

    private function remappingForwardPayload($forwardPayload, &$originStepPayload)
    {
        if (!$forwardPayload) {
            return;
        }

        foreach ($forwardPayload ?: [] as $fKey => $fPayload) {
            if (is_array($fPayload)) {
                if (!isset($realPayload[$fKey]) || !is_array($realPayload[$fKey])) {
                    $realPayload[$fKey] = [];
                }

                $this->remappingForwardPayload($fPayload, $originStepPayload[$fKey]);
            } else {
                $originStepPayload[$fKey] = $fPayload;
            }
        }
    }

    private function mergeForwardPayloadToPayload($forwardPayload, &$realPayload)
    {
        if (!$forwardPayload) {
            return;
        }

        foreach ($forwardPayload ?: [] as $fKey => $fPayload) {
            if (is_array($fPayload)) {
                if (!isset($realPayload[$fKey]) || !is_array($realPayload[$fKey])) {
                    $realPayload[$fKey] = [];
                }

                $this->mergeForwardPayloadToPayload($fPayload, $realPayload[$fKey]);
            } else {
                $realPayload[$fKey] = $fPayload;
            }
        }
    }

}

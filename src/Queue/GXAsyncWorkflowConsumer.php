<?php

namespace GlobalXtreme\RabbitMQ\Queue;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitAsyncWorkflowStatus;
use GlobalXtreme\RabbitMQ\Constant\GXRabbitConnectionType;
use GlobalXtreme\RabbitMQ\Constant\GXRabbitMessageDeliveryStatus;
use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflow;
use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflowStep;
use GlobalXtreme\RabbitMQ\Models\GXRabbitConnection;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class GXAsyncWorkflowConsumer
{
    /**
     * @var array
     */
    protected array $queues = [];


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
            connection_timeout: (config('gx-rabbitmq.timeout') ?: 60)
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

        try {
            $channel->consume();
        } catch (\Throwable $exception) {
            Log::error($exception);
        } finally {
            $channel->close();
            $connection->close();
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
                    $query->where('service', $serviceName)->where('queue', $queue);
                }
            ])->find($body['workflowId']);
            if (!$workflow) {
                $this->failedConsuming($consumer, null, null, "Async workflow Not found [{$body['workflowId']}]");
                return;
            }

            if ($workflow->statusId == GXRabbitAsyncWorkflowStatus::FINISH_ID) {
                $this->failedConsuming($consumer, $workflow, null, "Your async workflow already finished [{$body['workflowId']}]");
                return;
            }

            $workflowStep = $workflow->steps->where('queue', $queue)->first();
            if (!$workflowStep) {
                $this->failedConsuming($consumer, $workflow, null, "Async workflow Not found [{$body['workflowId']}]");
                return;
            }

            $nextWorkflowStep = null;
            if ($workflowStep->statusId == GXRabbitAsyncWorkflowStatus::FINISH_ID) {
                $nextWorkflowStep = $workflow->steps()->where('stepOrder', '>', $workflowStep->stepOrder)
                    ->orderBy('stepOrder', 'ASC')
                    ->first();
                if (!$nextWorkflowStep || $nextWorkflowStep->statusId == GXRabbitAsyncWorkflowStatus::FINISH_ID) {
                    $this->failedConsuming($consumer, $workflow, $workflowStep, "Your all async workflow step already finished [{$body['workflowId']}]");
                    return;
                }

                $response = $consumer::response($workflowStep, $data);
            } else {
                $response = $consumer::consume($workflowStep, $data);
            }

            $this->successConsuming($workflow, $workflowStep, $nextWorkflowStep, $response);

            Log::info("RABBITMQ-SUCCESS: $consumer " . now()->format('Y-m-d H:i:s'));

        } catch (\Throwable $throwable) {
            $this->failedConsuming($consumer, $workflow, $workflowStep, $throwable);
            Log::error($throwable);
        }
    }

    private function failedConsuming($consumer, $workflow, $workflowStep, \Throwable|string $throwable)
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

            if ($workflowStep) {
                if ($workflowStep instanceof GXRabbitAsyncWorkflowStep) {
                    $errors = $workflowStep->errors ?: [];
                    $errors[] = $exceptionAttribute;

                    $workflowStep->errors = $errors;
                    $workflowStep->statusId = GXRabbitAsyncWorkflowStatus::ERROR_ID;
                    $workflowStep->save();
                }
            } else {
                if ($workflow instanceof GXRabbitAsyncWorkflow) {
                    $errors = $workflow->errors ?: [];
                    $errors[] = $exceptionAttribute;

                    $workflow->errors = $errors;
                    $workflow->statusId = GXRabbitAsyncWorkflowStatus::ERROR_ID;
                    $workflow->save();
                }
            }
        }

        $this->sendNotification($workflow, $workflowStep, $exceptionAttribute['message']);
    }

    public function successConsuming($workflow, $workflowStep, $nextWorkflowStep, $response = null)
    {
        if (!$nextWorkflowStep) {
            $nextWorkflowStep = $workflow->steps()->where('stepOrder', '>', $workflowStep->stepOrder)
                ->orderBy('stepOrder', 'ASC')
                ->first();
        }

        if (!$nextWorkflowStep && $workflow->statusId != GXRabbitAsyncWorkflowStatus::FINISH_ID) {
            $workflow->statusId = GXRabbitAsyncWorkflowStatus::FINISH_ID;
            $workflow->save();
        }

        if ($workflowStep->statusId != GXRabbitAsyncWorkflowStatus::FINISH_ID) {
            $workflowStep->response = $response;
            $workflowStep->statusId = GXRabbitAsyncWorkflowStatus::FINISH_ID;
            $workflowStep->save();
        }

        if ($nextWorkflowStep) {
            $nextWorkflowStep->payload = $response;
            $nextWorkflowStep->save();

            if ($nextWorkflowStep->statusId != GXRabbitAsyncWorkflowStatus::FINISH_ID) {
                $publish = new GXAsyncWorkflowPublish();
                $publish->pushWorkflowMessage($workflow->id, $workflowStep->id, $response);
            }
        }

        if ($workflow->statusId == GXRabbitAsyncWorkflowStatus::FINISH_ID) {
            $this->sendNotification($workflow, $workflowStep, $workflow->successMessage);
        }
    }

    private function sendNotification($workflow, $workflowStep, $message)
    {
        // TODO: Kedepannya akan mengirim ke firebase
    }

    /**
     * @param string $message
     *
     * @return mixed
     * @throws \Exception
     */
    private function logError(string $message)
    {
        Log::error("ASYNC-WORKFLOW-CONSUMER: $message");
        $this->isError = true;

        throw new \Exception($message);
    }

}

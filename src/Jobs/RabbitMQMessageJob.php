<?php

namespace GlobalXtreme\RabbitMQ\Jobs;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitKeyConstant;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RabbitMQMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param $data
     */
    public function __construct(public $data)
    {
        if (is_string($this->data)) {
            $this->data = json_decode($this->data, true);
        }
    }


    public function handle()
    {
        try {

            if (!isset($this->data['key']) || !$this->data['key']) {
                $this->logError("Your key [{$this->data['key']}] invalid!");
                return;
            }

            $key = $this->data['key'];
            $queueMessage = GXRabbitMessage::find($this->data['messageId']);

            $service = GXRabbitKeyConstant::callMessageClass($queueMessage, $key);
            if (!$service) {
                $this->logError("Message broker key does not exists or not yet set service class! [$key]");
                return;
            }

            $service->handle(($key == GXRabbitKeyConstant::FAILED_SAVE) ? $this->data : $this->data['message']);

            if (isset($this->data['failedId']) && $this->data['failedId']) {
                success_repair_message_broker($this->data['messageId'], $this->data['failedId'], $this->data['queue'], $this->data['key']);
            }

        } catch (\Exception $exception) {
            Log::error($exception);
            $this->sendMessageFailed($exception);
        }
    }


    /*
     |-------------------------------------------------------------------------
     | Functions
     |-------------------------------------------------------------------------
     */

    private function logError(string $string)
    {
        Log::error("RabbitMQ-Consume: $string");

        $this->sendMessageFailed($string);
    }

    private function sendMessageFailed(\Exception|string|null $exception)
    {
        failed_message_broker(
            $this->data['exchange'],
            $this->data['queue'],
            $this->data['key'],
            $this->data['message'],
            $exception,
            messageId: isset($this->data['messageId']) ? $this->data['messageId'] : null,
            failedId: isset($this->data['failedId']) ? $this->data['failedId'] : null
        );
    }

}
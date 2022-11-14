<?php

namespace GlobalXtreme\RabbitMQ\Consumer;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitKeyConstant;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessageFailed;
use GlobalXtreme\RabbitMQ\Queue\GXRabbitMQQueue;
use Illuminate\Support\Facades\Log;

class RepairFailedMessage
{
    /**
     * @param GXRabbitMessageFailed $messageFailed
     */
    public function __construct(protected GXRabbitMessageFailed $messageFailed)
    {
    }

    /**
     * @return void
     */
    public function handle()
    {
        try {

            GXRabbitMQQueue::dispatch($this->messageFailed->payload)
                ->onExchange('failed')
                ->onQueue($this->messageFailed->queue)
                ->onKey($this->messageFailed->key)
                ->onFailedId($this->messageFailed->id);

            $this->messageFailed->retry = $this->messageFailed->retry + 1;
            if ($this->messageFailed->retry >= GXRabbitKeyConstant::MAX_RETRY) {
                $this->messageFailed->rested = true;
            }

            $this->messageFailed->save();

        } catch (\Exception $exception) {
            Log::error($exception);
        }
    }

}

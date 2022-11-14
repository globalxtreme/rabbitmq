<?php

namespace App\Console\Commands\MessageBroker;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitKeyConstant;
use GlobalXtreme\RabbitMQ\Consumer\RepairFailedMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessageFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RepairFailedMessageCommand extends Command
{
    protected $signature = 'message-broker:repair-failed-message {--ID=}';
    protected $description = 'Repair message broker failed';

    public function handle()
    {
        try {

            $messageFaileds = GXRabbitMessageFailed::where('retry', '<', GXRabbitKeyConstant::MAX_RETRY)
                ->where('sender', config('queue.connections.rabbitmq.queue'))
                ->where('repaired', false)
                ->where('rested', false)
                ->where(function ($query) {

                    $failedId = $this->option('ID');
                    if ($failedId) {
                        $query->where('id', $failedId);
                    }

                })->get();
            foreach ($messageFaileds as $messageFailed) {
                $repairMessage = new RepairFailedMessage($messageFailed);
                $repairMessage->handle();

                $this->info($messageFailed->subject);
            }

        } catch (\Exception $exception) {
            Log::debug("message-broker-repair-failed-message-command");
            Log::error($exception);
        }
    }
}

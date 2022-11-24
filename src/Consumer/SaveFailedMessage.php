<?php

namespace GlobalXtreme\RabbitMQ\Consumer;

use GlobalXtreme\RabbitMQ\Mail\FailedMessageMail;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessage;
use GlobalXtreme\RabbitMQ\Models\GXRabbitMessageFailed;
use GlobalXtreme\RabbitMQ\ThirdParty\Telegram;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SaveFailedMessage
{
    /**
     * @var GXRabbitMessage
     */
    protected $rabbitMessage;


    /**
     * @param GXRabbitMessage $rabbitMessage
     */
    public function __construct(GXRabbitMessage $rabbitMessage)
    {
        $this->rabbitMessage = $rabbitMessage;
    }


    /**
     * @param array|string $data
     *
     * @return void
     */
    public function handle($data)
    {
        try {

            DB::transaction(function () use ($data) {

                $subject = "[{$data['queue']}] {$data['failedKey']}. ID: " . ($data['failedId'] ?: '{failed-id}');

                if (!$data['failedId']) {

                    $failedQueue = GXRabbitMessageFailed::create([
                        'messageId' => $this->rabbitMessage->id,
                        'subject' => $subject,
                        'queueSender' => $this->rabbitMessage->queueSender,
                        'queueConsumer' => $data['queue'],
                        'key' => $data['failedKey'],
                        'payload' => $data['message'],
                        'exception' => $data['exception'],
                    ]);
                    if ($failedQueue) {
                        $failedQueue->subject = str_replace('{failed-id}', $failedQueue->id, $failedQueue->subject);
                        $failedQueue->save();
                    }

                } else {
                    $failedQueue = GXRabbitMessageFailed::find($data['failedId']);
                }

                if ($failedQueue) {

                    $message = '';
                    if (isset($data['success']) && $data['success']) {
                        $failedQueue->repaired = true;
                        $failedQueue->save();

                        $message = "*SUCCESS:* \n";
                    }

                    $message .= "*Message broker failed*\n\n";
                    $message .= $failedQueue->subject;

                    $this->sendToTelegram($message);

                    if (!$failedQueue->repaired) {
                        $this->sendToEmail($failedQueue);
                    }

                }

            });

        } catch (\Exception $exception) {
            Log::info($exception);
        }
    }


    /** --- SUB FUNCTIONS --- */

    private function sendToTelegram(string $message)
    {
        $telegram = new Telegram();
        $telegram->queue($message);
    }

    private function sendToEmail($failedQueue)
    {
        $exception = $failedQueue->exception;
        $message = (new FailedMessageMail($failedQueue->subject, $exception['message'], $exception['trace']))
            ->onConnection('database')
            ->onQueue('default');

        Mail::to(config('mail.dev-emails'))
            ->queue($message);

    }

}

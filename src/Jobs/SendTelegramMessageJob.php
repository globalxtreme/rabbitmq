<?php

namespace GlobalXtreme\RabbitMQ\Jobs;

use App\ThirdParty\Telegram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    public $message;


    /**
     * @param string $message
     */
    public function __construct(string $message)
    {
        $this->message = $message;
    }


    public function handle()
    {
        $telegram = new Telegram();
        $telegram->send($this->message);
    }
}

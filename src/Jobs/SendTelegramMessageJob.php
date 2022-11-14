<?php

namespace App\Jobs;

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
     * @param string $message
     */
    public function __construct(public string $message)
    {
    }


    public function handle()
    {
        $telegram = new Telegram();
        $telegram->send($this->message);
    }
}

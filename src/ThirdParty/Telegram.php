<?php

namespace App\ThirdParty;

use App\Jobs\SendTelegramMessageJob;
use GlobalXtreme\Response\Constant\ResponseConstant;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class Telegram
{
    const BASE_URL = 'https://api.telegram.org/';

    protected $bootToken = '1285522820:AAEzeBi2iz-Z0_1ua55G9jHg8EMKE8nXcSk';
    protected $roomChatId = '-485588353';


    /**
     * @param string $messages
     *
     * @return false|void
     */
    public function send(string $messages)
    {
        if (strlen($messages) <= 0) {
            return false;
        }

        $limit = 3000;
        $messageStrReplace = str_replace("\r\n", "<>", $messages);
        $messageParser = explode("<=>", wordwrap($messageStrReplace, $limit, "<=>"));
        $messageContents = str_replace("<>", "\n", $messageParser);

        if (count($messageContents) == 1) {
            $this->sendContentTelegram($messageContents[0]);
        } else {
            foreach ($messageContents as $key => $messageContent) {
                $this->sendContentTelegram($messageContents[$key]);
            }
        }
    }

    /**
     * @param string $message
     * @param bool $now
     *
     * @return void
     */
    public function queue(string $message, bool $now = true)
    {
        if ($now) {
            SendTelegramMessageJob::dispatchNow($message);
        } else {
            SendTelegramMessageJob::dispatch($message)
                ->onConnection(config('queue.default'))
                ->onQueue('default');
        }
    }


    /** --- FUNCTIONS --- */

    private function sendContentTelegram(string $message)
    {
        try {
            $url = self::BASE_URL . "bot$this->bootToken/sendMessage";

            $client = new Client();
            $response = $client->request("POST", $url, [
                "form_params" => [
                    'chat_id' => $this->roomChatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ],
                "timeout" => 10
            ]);

            if ($response->getStatusCode() == ResponseConstant::HTTP_STATUS_CODE['SUCCESS']) {
                return json_decode($response->getBody(), true);
            } else {
                Log::debug("GX-RabbitMQ-Third: Telegram {$response->getStatusCode()}");
            }

            return false;
        } catch (\Exception $exception) {
            Log::error($exception);
            return false;
        }
    }

}

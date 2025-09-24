<?php

namespace GlobalXtreme\RabbitMQ\PrivateAPI;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Log;

class BusinessWorkflowAPI
{
    /**
     * @param $payload
     *
     * @return mixed|null
     */
    public static function notificationPush($payload)
    {
        return static::call("POST", 'notifications', $payload);
    }


    /** --- PROTECTED FUNCTIONS --- */

    /**
     * @param $method
     * @param $endpoint
     * @param $payload
     *
     * @return mixed|null
     */
    protected static function call($method, $endpoint, $payload = [])
    {
        try {
            // Create a guzzle client
            $client = new Client();

            // Forward the request and get the response.
            $url = static::setURL($endpoint);
            if ($url == '') {
                self::errPrivateAPI("Please set CLIENT_PRIVATE_API_BUSINESS_WORKFLOW_HOST environment variable");
                return null;
            }

            $response = $client->request($method, $url, self::prepare($payload));
            if (!$response) {
                return null;
            }

            // Set response body
            $body = json_decode($response->getBody());

            // Check http status code
            $statusCode = $response->getStatusCode();
            if ($statusCode > 299) {
                if ($status = optional($body)->status) {
                    $errorMessage = sprintf("%s. %s. %d", $status->message, $status->internalMsg, $statusCode);
                } else {
                    $errorMessage = "Push notification to business private api is failed";
                }

                self::errPrivateAPI($errorMessage);
                return null;
            }

            return $body;

        } catch (\Throwable $e) {
            $errorMessage = "Push notification to business private api is failed";
            if ($e instanceof BadResponseException) {
                $body = json_decode($e->getResponse()->getBody());
                if (optional($body)->status) {
                    $status = $body->status;
                    $errorMessage = sprintf("%s. %s. %d", $status->message, $status->internalMsg, $status->code);
                }
            }

            self::errPrivateAPI($errorMessage);
            return null;
        }
    }

    /**
     * @param $endpoint
     *
     * @return string
     */
    protected static function setURL($endpoint)
    {
        $host = env('CLIENT_PRIVATE_API_BUSINESS_WORKFLOW_HOST');
        if (!$host || $host == "") {
            return '';
        }

        return "$host/$endpoint";
    }

    /**
     * @param $payload
     *
     * @return array
     */
    protected static function prepare($payload = [])
    {
        $headers = self::setHeaders();
        if (!$headers) {
            return null;
        }

        // Default options
        $options = [
            'headers' => $headers,
        ];

        // Set Contents
        if ($payload && count($payload) > 0) {
            $options['json'] = $payload;
        }

        return $options;
    }

    /**
     * @return array
     */
    protected static function setHeaders()
    {
        $clientId = env('CLIENT_PRIVATE_API_BUSINESS_WORKFLOW_ID');
        $clientName = env('CLIENT_PRIVATE_API_BUSINESS_WORKFLOW_NAME');
        $clientSecret = env('CLIENT_PRIVATE_API_BUSINESS_WORKFLOW_SECRET');
        if ($clientId == "" || $clientId == "" || $clientId == "") {
            self::errPrivateAPI("Please set your client id, name, and secret environment variable");
            return null;
        }

        return [
            'Content-Type' => 'application/json',
            'Client-ID' => $clientId,
            'Client-Name' => $clientName,
            'Client-Secret' => $clientSecret,
        ];
    }

    private static function errPrivateAPI($message)
    {
        Log::error("PRIVATE_API-BUSINESS-WORKFLOW: $message");
    }

}

<?php

namespace GlobalXtreme\RabbitMQ\Constant;

use GlobalXtreme\RabbitMQ\Consumer\SaveFailedMessage;

class GXRabbitKeyConstant
{
    const MAX_RETRY = 10;

    const FAILED_SAVE = 'failed-save';

    const MESSAGES = [
        self::FAILED_SAVE => SaveFailedMessage::class,
    ];


    /**
     * @param string $key
     *
     * @return mixed
     */
    public static function callMessageClass(string $key)
    {
        $class = static::MESSAGES[$key];
        return new $class();
    }

}

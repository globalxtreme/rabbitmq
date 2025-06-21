<?php

namespace GlobalXtreme\RabbitMQ\Constant;

use GlobalXtreme\RabbitMQ\Constant\Base\BaseIDName;

class GXRabbitMessageDeliveryStatus extends BaseIDName
{
    const PENDING_ID = 1;
    const PENDING = 'Pending';
    const FINISH_ID = 2;
    const FINISH = 'Finish';
    const ERROR_ID = 3;
    const ERROR = 'Error';

    const OPTION = [
        self::PENDING_ID => self::PENDING,
        self::FINISH_ID => self::FINISH,
        self::ERROR_ID => self::ERROR,
    ];

}
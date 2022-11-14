<?php

namespace GlobalXtreme\RabbitMQ\Constant;

use GlobalXtreme\RabbitMQ\Constant\Supports\BaseCodeName;

class GXRabbitQueueConstant extends BaseCodeName
{
    const CUSTOMER_SUPPORT = 'customer-support';
    const INVENTORY = 'inventory';

    const OPTION = [
        self::CUSTOMER_SUPPORT,
        self::INVENTORY,
    ];

}

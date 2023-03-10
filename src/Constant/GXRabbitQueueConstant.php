<?php

namespace GlobalXtreme\RabbitMQ\Constant;

use GlobalXtreme\RabbitMQ\Constant\Supports\BaseCodeName;

class GXRabbitQueueConstant extends BaseCodeName
{
    const BUSINESS_MANAGEMENT = 'business-management';
    const CUSTOMER_SUPPORT = 'customer-support';
    const INVENTORY = 'inventory';

    const OPTION = [
        self::BUSINESS_MANAGEMENT => self::BUSINESS_MANAGEMENT,
        self::CUSTOMER_SUPPORT => self::CUSTOMER_SUPPORT,
        self::INVENTORY => self::INVENTORY,
    ];

}

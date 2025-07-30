<?php

namespace GlobalXtreme\RabbitMQ\Constant;

use GlobalXtreme\RabbitMQ\Constant\Base\BaseIDName;

class GXRabbitAsyncWorkflowStatus extends BaseIDName
{
    const PENDING_ID = 1;
    const PENDING = 'Pending';
    const PROCESSING_ID = 2;
    const PROCESSING = 'Processing';
    const SUCCESS_ID = 3;
    const SUCCESS = 'Success';
    const ERROR_ID = 4;
    const ERROR = 'Error';

    const OPTION = [
        self::PENDING_ID => self::PENDING,
        self::PROCESSING_ID => self::PROCESSING,
        self::SUCCESS_ID => self::SUCCESS,
        self::ERROR_ID => self::ERROR,
    ];

}

<?php

namespace GlobalXtreme\RabbitMQ\Constant;

use GlobalXtreme\RabbitMQ\Constant\Base\BaseIDName;

class GXRabbitAsyncWorkflowStatus extends BaseIDName
{
    const PENDING_ID = 1;
    const PENDING = 'Pending';
    const PROCESSING_ID = 2;
    const PROCESSING = 'Processing';
    const FINISH_ID = 3;
    const FINISH = 'Finish';
    const ERROR_ID = 4;
    const ERROR = 'Error';

    const OPTION = [
        self::PENDING_ID => self::PENDING,
        self::PROCESSING_ID => self::PROCESSING,
        self::FINISH_ID => self::FINISH,
        self::ERROR_ID => self::ERROR,
    ];

}

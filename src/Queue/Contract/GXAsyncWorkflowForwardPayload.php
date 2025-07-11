<?php

namespace GlobalXtreme\RabbitMQ\Queue\Contract;

use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflowStep;

interface GXAsyncWorkflowForwardPayload
{
    /**
     * @return mixed
     */
    public function forwardPayload();

}

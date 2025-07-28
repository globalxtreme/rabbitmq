<?php

namespace GlobalXtreme\RabbitMQ\Queue\Contract;

interface GXAsyncWorkflowForwardPayload
{
    /**
     * @return mixed
     */
    public function forwardPayload();

}

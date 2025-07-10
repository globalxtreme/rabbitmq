<?php

namespace GlobalXtreme\RabbitMQ\Queue\Contract;

interface GXAsyncWorkflowConsumerContract
{
    /**
     * @return array|null
     */
    public function consume();

    /**
     * @param $data
     *
     * @return mixed
     */
    public function response($data = null);

}

<?php

namespace GlobalXtreme\RabbitMQ\Form;

class GXAsyncWorkflowForwardPayloadForm
{
    /**
     * @var int
     */
    public $stepOrder = 0;

    /**
     * @var array|null
     */
    public $payload = null;


    /**
     * @param $stepOrder
     * @param $payload
     */
    public function __construct($stepOrder = 0, $payload = null)
    {
        $this->stepOrder = $stepOrder;
        $this->payload = $payload;
    }
}

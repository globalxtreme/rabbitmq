<?php

namespace GlobalXtreme\RabbitMQ\Form;

class GXAsyncWorkflowForm
{
    /**
     * @var string
     */
    public $service;

    /**
     * @var string|null
     */
    public $queue = null;

    /**
     * @var string|null
     */
    public $description = null;

    /**
     * @var array|null
     */
    public $payload = null;

    /**
     * @var int
     */
    public $stepOrder = 0;


    /**
     * @param string $service
     * @param string|null $queue
     * @param string|null $description
     * @param array|null $payload
     */
    public function __construct($service, $queue = null, $description = null, $payload = null)
    {
        $this->service = $service;
        $this->queue = $queue;
        $this->description = $description;
        $this->payload = $payload;
    }
}

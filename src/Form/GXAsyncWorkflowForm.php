<?php

namespace GlobalXtreme\RabbitMQ\Form;

class GXAsyncWorkflowForm
{
    /**
     * @var int
     */
    public int $stepOrder = 0;


    /**
     * @param string $service
     * @param string|null $queue
     * @param string|null $description
     * @param array|null $payload
     */
    public function __construct(
        public string      $service,
        public string|null $queue = null,
        public string|null $description = null,
        public array|null  $payload = null,
    )
    {
    }
}

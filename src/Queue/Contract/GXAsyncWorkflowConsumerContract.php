<?php

namespace GlobalXtreme\RabbitMQ\Queue\Contract;

use GlobalXtreme\RabbitMQ\Models\GXRabbitAsyncWorkflowStep;

interface GXAsyncWorkflowConsumerContract
{
    /**
     * @param GXRabbitAsyncWorkflowStep $workflowStep
     * @param array $data
     *
     * @return array|null
     */
    public static function consume(GXRabbitAsyncWorkflowStep $workflowStep, array $data);

    /**
     * @param GXRabbitAsyncWorkflowStep $workflowStep
     * @param array $data
     * @param ...$processedData
     *
     * @return mixed
     */
    public static function response(GXRabbitAsyncWorkflowStep $workflowStep, array $data, ...$processedData);

}

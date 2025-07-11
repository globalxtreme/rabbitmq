<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;

class GXRabbitAsyncWorkflowStep extends BaseModel
{
    protected $table = 'async_workflow_steps';
    protected $guarded = ['id'];

    protected $fillable = [
        'workflowId',
        'service',
        'queue',
        'stepOrder',
        'reprocessed',
        'statusId',
        'description',
        'payload',
        'forwardPayload',
        'errors',
        'response',
    ];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];
    protected $casts = [
        'reprocessed' => 'integer',
        'payload' => 'array',
        'forwardPayload' => 'array',
        'errors' => 'array',
        'response' => 'array',
    ];


    /** --- SCOPES --- */

    public function scopeFilter($query, $request)
    {
        return $query;
    }

}

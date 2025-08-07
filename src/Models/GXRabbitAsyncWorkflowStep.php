<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;

class GXRabbitAsyncWorkflowStep extends BaseModel
{
    protected $table = 'async_workflow_steps';
    protected $guarded = ['id'];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];
    protected $casts = [
        'reprocessed' => 'integer',
        'payload' => 'array',
        'forwardPayload' => 'array',
        'errors' => 'array',
        'response' => 'array',
    ];


    /** --- RELATIONSHIPS --- */

    public function workflow()
    {
        return $this->belongsTo(GXRabbitAsyncWorkflow::class, 'workflowId');
    }


    /** --- SCOPES --- */

    public function scopeFilter($query, $request)
    {
        return $query;
    }

}

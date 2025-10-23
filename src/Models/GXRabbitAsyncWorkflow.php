<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Constant\GXRabbitAsyncWorkflowStatus;
use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GXRabbitAsyncWorkflow extends BaseModel
{
    protected $table = 'async_workflows';
    protected $guarded = ['id'];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT, 'allowResendAt'];
    protected $casts = [
        'totalStep' => 'integer',
        'reprocessed' => 'integer',
        'errors' => 'array',
    ];


    /** --- RELATIONSHIPS --- */

    public function steps(): HasMany
    {
        return $this->hasMany(GXRabbitAsyncWorkflowStep::class, 'workflowId');
    }


    /** --- SCOPES --- */

    public function scopeFilter($query, $request)
    {
        if ($request->action != '') {
            $query->where('action', $request->action);
        }

        if ($request->statusId != '') {
            $query->where('statusId', $request->statusId);
        }

        if ($request->referenceId != '') {
            $query->where('referenceId', $request->referenceId);
        }

        return $query;
    }

    public function scopeFindForResend($query, $id)
    {
        return $query->with([
            'steps' => function ($query) {
                $query->where('statusId', GXRabbitAsyncWorkflowStatus::ERROR_ID);
            }
        ])->find($id);
    }

}

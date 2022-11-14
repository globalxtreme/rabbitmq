<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GXRabbitKey extends BaseModel
{
    protected $table = 'keys';

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];


    /** --- SCOPES --- */

    public function scopeFilter($query, $request)
    {
        return $query->where(function ($query) use ($request) {

            if ($request->queueId) {
                $query->where('queueId', $request->queueId);
            }

            if ($this->hasSearch($request)) {
                $query->where('name', 'LIKE', "%$request->search%");
            }

        });
    }


    /** --- RELATIONSHIPS --- */

    public function queue(): BelongsTo
    {
        return $this->belongsTo(GXRabbitQueue::class, 'queueId');
    }

}

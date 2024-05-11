<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GXRabbitMessageFailed extends BaseModel
{
    protected $table = 'message_faileds';
    protected $guarded = [''];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];
    protected $casts = [
        'payload' => 'array',
        'exception' => 'array',
    ];


    /** --- RELATIONSHIPS --- */

    public function message(): BelongsTo
    {
        return $this->belongsTo(GXRabbitMessage::class, 'messageId');
    }


    /** --- SCOPES --- */

    public function scopeFilter($query, $request)
    {
        return $query->where(function ($query) use ($request) {

            if ($request->messageId) {
                $query->where('messageId', $request->messageId);
            }

            if ($request->queue) {
                $query->where('queue', $request->queue);
            }

            if ($request->key) {
                $query->where('key', $request->key);
            }

            if ($request->repaired != '') {
                $query->where('repaired', $request->repaired);
            }

            if ($request->rested != '') {
                $query->where('rested', $request->rested);
            }

            if ($this->hasSearch($request)) {
                $query->where('subject', 'LIKE', "%$request->search%");
            }

        });
    }

}

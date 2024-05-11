<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GXRabbitMessage extends BaseModel
{
    protected $table = 'messages';
    protected $guarded = [''];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];
    protected $casts = [
        'queueConsumers' => 'array',
        'payload' => 'array',
        'statuses' => 'array',
        'finished' => 'boolean',
    ];


    /** --- RELATIONSHIPS --- */

    public function sender(): MorphTo
    {
        return $this->morphTo('sender', 'senderType', 'senderId');
    }

    public function faileds(): HasMany
    {
        return $this->hasMany(GXRabbitMessageFailed::class, 'messageId');
    }


    /** --- SCOPES --- */

    public function scopeFilter($query, $request)
    {
        return $query->where(function ($query) use ($request) {

            if ($request->exchange) {
                $query->where('exchange', $request->exchange);
            }

            if ($request->queue) {
                $query->where('queue', $request->queue);
            }

            if ($request->key) {
                $query->where('key', $request->key);
            }

        });
    }

}

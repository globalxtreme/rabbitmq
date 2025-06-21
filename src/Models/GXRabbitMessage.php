<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GXRabbitMessage extends BaseModel
{
    protected $table = 'messages';
    protected $fillable = [
        'connectionId',
        'exchange',
        'queue',
        'senderId',
        'senderType',
        'senderService',
        'payload',
        'finished',
    ];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];
    protected $casts = [
        'payload' => 'array',
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

    public function deliveries(): HasMany
    {
        return $this->hasMany(GXRabbitMessageDelivery::class, 'messageId');
    }


    /** --- SCOPES --- */

    public function scopeFilter($query, $request)
    {
        return $query->where(function ($query) use ($request) {

            if ($request->connectionId != '') {
                $query->where('connectionId', $request->connectionId);
            }

            if ($request->exchange != '') {
                $query->where('exchange', $request->exchange);
            }

            if ($request->queue != '') {
                $query->where('queue', $request->queue);
            }

            if ($request->finished != '') {
                $query->where('finished', $request->finished);
            }

        });
    }

}

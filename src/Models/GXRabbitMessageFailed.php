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
        'resend' => 'boolean',
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

            if ($request->service) {
                $query->where('service', $request->service);
            }

            if ($request->exchange) {
                $query->where('exchange', $request->exchange);
            }

            if ($request->queue) {
                $query->where('queue', $request->queue);
            }

            if ($request->resend != '') {
                $query->where('resend', $request->resend);
            }

        });
    }

}

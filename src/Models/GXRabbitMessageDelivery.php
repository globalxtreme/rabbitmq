<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GXRabbitMessageDelivery extends BaseModel
{
    protected $table = 'message_deliveries';
    protected $fillable = [
        'messageId',
        'consumerService',
        'statusId',
        'responses',
        'needNotification',
    ];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];
    protected $casts = [
        'responses' => 'array',
        'needNotification' => 'boolean',
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

            if ($request->service != "") {
                $query->where('service', $request->service);
            }

            if ($request->statusId != "") {
                $query->where('statusId', $request->statusId);
            }

        });
    }

}

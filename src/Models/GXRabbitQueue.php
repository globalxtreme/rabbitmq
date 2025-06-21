<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;

class GXRabbitQueue extends BaseModel
{
    protected $table = 'queues';
    protected $guarded = ['id'];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];
    protected $casts = [
        'payload' => 'array'
    ];


    /** --- SCOPES --- */

    public function scopeFilter($query, $request)
    {
        return $query->where(function ($query) use ($request) {

            if ($this->hasSearch($request)) {
                $query->where('name', 'LIKE', "%$request->search%");
            }

        });
    }

    public function scopeOfName($query, $name)
    {
        return $query->where('name', $name);
    }

}

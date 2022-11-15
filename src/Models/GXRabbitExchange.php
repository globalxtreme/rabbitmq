<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;

class GXRabbitExchange extends BaseModel
{
    protected $table = 'exchanges';
    protected $guarded = [''];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];


    /** --- SCOPES --- */

    public function scopeFilter($query, $request)
    {
        return $query->where(function ($query) use ($request) {

            if ($this->hasSearch($request)) {
                $query->where('name', 'LIKE', "%$request->search%");
            }

        });
    }

}

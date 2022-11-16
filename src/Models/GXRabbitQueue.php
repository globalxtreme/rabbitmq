<?php

namespace GlobalXtreme\RabbitMQ\Models;

use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GXRabbitQueue extends BaseModel
{
    protected $table = 'queues';
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

    public function scopeOfName($query, $name)
    {
        return $query->where('name', $name);
    }


    /** --- RELATIONSHIPS --- */

    public function keys(): HasMany
    {
        return $this->hasMany(GXRabbitKey::class, 'queueId');
    }

}

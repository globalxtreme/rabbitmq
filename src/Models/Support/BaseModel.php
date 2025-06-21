<?php

namespace GlobalXtreme\RabbitMQ\Models\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseModel extends Model
{
    use SoftDeletes;

    // Custom date times column
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';
    const DELETED_AT = 'deletedAt';


    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection(config('gx-rabbitmq.connection.database'));
    }


    /** --- RELATIONS --- */

    public function scopeOfDate($query, $key = 'date', $fromDate = null, $toDate = null)
    {
        $fromDate = $fromDate ?: Carbon::now()->subMonth()->format('d/m/Y');
        $toDate = $toDate ?: Carbon::now()->format('d/m/Y');

        $fromDate = Carbon::createFromFormat('d/m/Y', $fromDate)->startOfDay();
        $toDate = Carbon::createFromFormat('d/m/Y', $toDate)->endOfDay();

        if ($fromDate->gt($toDate)) {
            $fromDate = clone $toDate;
            $fromDate->startOfDay();
        }

        return $query->whereBetween($key, [$fromDate, $toDate]);
    }

    public function scopeGetOrPaginate($query, $request, $forcePagination = false)
    {
        $pagination = $forcePagination ?: false;
        if ($request->has('page')) {
            $pagination = $request->page ? true : false;
        }

        if ($pagination) {
            return $query->paginate($request->limit ?: 50);
        } else {
            return $query->get();
        }
    }


    /** --- FUNCTIONS --- */

    public function hasSearch($request): bool
    {
        return $request->has('search') && strlen($request->search) >= 3;
    }

}

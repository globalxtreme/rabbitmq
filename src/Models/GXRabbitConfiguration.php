<?php

namespace GlobalXtreme\RabbitMQ\Models;

use Carbon\Carbon;
use GlobalXtreme\RabbitMQ\Models\Support\BaseModel;

class GXRabbitConfiguration extends BaseModel
{
    protected $table = 'configurations';
    protected $guarded = ['id'];

    protected $dates = [self::CREATED_AT, self::UPDATED_AT, self::DELETED_AT];


    /** --- STATIC FUNCTIONS --- */

    public static function setAllowResendAt(): Carbon
    {
        $interval = 60;

        $config = self::where('name', 'allow-resend-workflow-interval')->first();
        if ($config->value) {
            $interval = (int)$config->value ?: 60;
        }

        return Carbon::now()->addMinutes($interval);
    }

}
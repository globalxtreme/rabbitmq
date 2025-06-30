<?php

namespace GlobalXtreme\RabbitMQ\Constant\Base;

use Illuminate\Support\Str;

class BaseCodeName
{
    const OPTION = [];

    private const CAPITALS = [];


    /** --- FUNCTIONS --- */

    /**
     * @param array|null $options
     *
     * @return array
     */
    public static function get($options = null)
    {
        if ($options) {
            return collect($options)->map(function ($option) {
                return ['code' => $option, 'name' => static::display($option)];
            })->toArray();
        }

        $data = [];
        foreach (static::OPTION as $value) {
            $data[] = ['code' => $value, 'name' => static::display($value)];
        }

        return $data;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public static function display(string $value)
    {
        $value = str_replace("_", " ", $value);
        $value = Str::title($value);

        foreach (self::CAPITALS as $CAPITAL) {

            $capitalTitle = Str::title($CAPITAL);
            if (stripos($value, $capitalTitle) !== false) {
                $value = str_replace($capitalTitle, $CAPITAL, $value);
            }

        }

        return $value;
    }

    /**
     * @param string|null $code
     *
     * @return array|null
     */
    public static function codeName($code = null)
    {
        if (!$code) {
            return null;
        }

        return ['code' => $code, 'name' => static::display($code)];
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public static function toCamelCase(string $value)
    {
        return Str::camel($value);
    }

}

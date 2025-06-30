<?php

namespace GlobalXtreme\RabbitMQ\Constant\Base;

class BaseIDName
{
    const UNKNOWN = 'unknown';

    const OPTION = [
        0 => self::UNKNOWN
    ];


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
                return ['id' => $option, 'name' => static::display($option)];
            })->toArray();
        }

        $data = [];
        foreach (static::OPTION as $key => $value) {
            $data[] = ['id' => $key, 'name' => $value];
        }

        return $data;
    }

    /**
     * @param int|null $id
     *
     * @return string
     */
    public static function display($id = null)
    {
        if (isset(static::OPTION[$id])) {
            return static::OPTION[$id];
        }

        return self::UNKNOWN;
    }

    /**
     * @param int|null $id
     *
     * @return array|null
     */
    public static function idName($id = null)
    {
        if (!$id) {
            return null;
        }

        return ['id' => $id, 'name' => static::display($id)];
    }

}

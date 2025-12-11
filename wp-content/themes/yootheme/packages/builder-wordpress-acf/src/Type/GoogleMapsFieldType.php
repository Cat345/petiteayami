<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use YOOtheme\Str;
use function YOOtheme\trans;

class GoogleMapsFieldType
{
    /**
     * @return array
     */
    public static function config()
    {
        $props = [
            'address',
            'coordinates',
            'zoom',
            'place_id',
            'street_number',
            'street_name',
            'street_name_short',
            'city',
            'state',
            'state_short',
            'post_code',
            'country',
            'country_short',
        ];

        $fields = [];

        foreach ($props as $prop) {
            $fields[$prop] = [
                'type' => 'String',
                'metadata' => [
                    'label' => trans(Str::titleCase(str_replace('_', ' ', $prop))),
                ],
            ];

            if (method_exists(__CLASS__, $prop)) {
                $fields[$prop]['extensions']['call'] = __CLASS__ . "::{$prop}";
            }
        }

        return compact('fields');
    }

    public static function coordinates($field)
    {
        return isset($field['lat'], $field['lng']) ? "{$field['lat']},{$field['lng']}" : '';
    }
}

<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Type;

use function YOOtheme\trans;

class MapsFieldType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'address' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Address'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::address',
                    ],
                ],

                'coordinates' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Coordinates'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::coordinates',
                    ],
                ],
            ],
        ];
    }

    public static function address($value)
    {
        return $value;
    }

    public static function coordinates($value): ?string
    {
        if (!class_exists(\Toolset_Addon_Maps_Common::class)) {
            return null;
        }

        $coordinates = \Toolset_Addon_Maps_Common::get_coordinates($value);
        return "{$coordinates['lat']},{$coordinates['lon']}";
    }
}

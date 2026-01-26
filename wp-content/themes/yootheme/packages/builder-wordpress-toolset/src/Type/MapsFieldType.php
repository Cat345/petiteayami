<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Type;

use YOOtheme\Builder\Source;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class MapsFieldType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
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

    /**
     * @param string $value
     * @return string
     */
    public static function address($value)
    {
        return $value;
    }

    /**
     * @param mixed $value
     * @return ?string
     */
    public static function coordinates($value): ?string
    {
        if (!class_exists(\Toolset_Addon_Maps_Common::class)) {
            return null;
        }

        $coordinates = \Toolset_Addon_Maps_Common::get_coordinates($value);
        return "{$coordinates['lat']},{$coordinates['lon']}";
    }
}

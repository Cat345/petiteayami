<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Source;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class SiteType extends Source\Type\SiteType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
    {
        $config = parent::config();

        $config['fields']['item_count'] = [
            'type' => 'Int',
            'metadata' => [
                'label' => trans('Item Count'),
            ],
        ];

        return $config;
    }
}

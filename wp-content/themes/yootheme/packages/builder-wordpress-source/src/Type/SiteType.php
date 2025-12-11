<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use function YOOtheme\trans;

class SiteType extends \YOOtheme\Builder\Source\Type\SiteType
{
    /**
     * @return array
     */
    public static function config()
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

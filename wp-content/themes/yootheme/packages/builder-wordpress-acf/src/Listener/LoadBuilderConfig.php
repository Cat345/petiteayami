<?php

namespace YOOtheme\Builder\Wordpress\Acf\Listener;

use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\Wordpress\Acf\AcfHelper;
use YOOtheme\Builder\Wordpress\Source\Helper;

class LoadBuilderConfig
{
    /**
     * @param BuilderConfig $config
     */
    public static function handle($config): void
    {
        if (!AcfHelper::isActive()) {
            return;
        }

        foreach (Helper::getPostTypes() as $type) {
            foreach (AcfHelper::groups('post', $type->name) as $group) {
                $options = [];

                foreach (array_column(acf_get_fields($group), 'name', 'label') as $label => $name) {
                    $options[] = ['value' => "field:{$name}", 'text' => $label];
                }

                $config->push("sources.{$type->name}OrderOptions", [
                    'label' => $group['title'],
                    'options' => $options,
                ]);
            }
        }
    }
}

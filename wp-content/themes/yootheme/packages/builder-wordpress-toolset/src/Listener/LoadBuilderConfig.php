<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Listener;

use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use YOOtheme\Builder\Wordpress\Toolset\Helper;

class LoadBuilderConfig
{
    /**
     * @param BuilderConfig $config
     */
    public static function handle($config): void
    {
        if (!Helper::isActive()) {
            return;
        }

        foreach (SourceHelper::getPostTypes() as $type) {
            foreach (Helper::groups('posts', $type->name) as $group) {
                $fields = Helper::fields('posts', $group->get_field_slugs(), false);
                $options = [];

                foreach (array_column($fields, 'slug', 'name') as $name => $slug) {
                    $options[] = ['value' => "field:wpcf-{$slug}", 'text' => $name];
                }

                $config->push("sources.{$type->name}OrderOptions", [
                    'label' => $group->get_display_name(),
                    'options' => $options,
                ]);
            }
        }
    }
}

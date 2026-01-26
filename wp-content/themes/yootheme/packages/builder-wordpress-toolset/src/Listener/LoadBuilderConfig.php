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
                $dateFields = [];

                foreach ($fields as $field) {
                    $option = [
                        'value' => "field:wpcf-{$field['slug']}",
                        'text' => $field['name'],
                    ];

                    $options[] = $option;

                    if ($field['type'] === 'date') {
                        $dateFields[] = $option;
                    }
                }

                if (!empty($dateFields)) {
                    $config->push("sources.{$type->name}DateFilterOptions", [
                        'label' => $group->get_display_name(),
                        'options' => $dateFields,
                    ]);
                }

                $config->push("sources.{$type->name}OrderOptions", [
                    'label' => $group->get_display_name(),
                    'options' => $options,
                ]);
            }
        }
    }
}

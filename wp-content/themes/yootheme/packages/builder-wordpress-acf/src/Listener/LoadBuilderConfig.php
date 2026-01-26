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
                $dateFields = [];

                foreach (acf_get_fields($group) as $field) {
                    $option = [
                        'value' => "field:{$field['name']}",
                        'text' => $field['label'],
                    ];

                    $options[] = $option;

                    if (in_array($field['type'], ['date_time_picker', 'date_picker'])) {
                        $dateFields[] = $option;
                    }
                }

                if (!empty($dateFields)) {
                    $config->push("sources.{$type->name}DateFilterOptions", [
                        'label' => $group['title'],
                        'options' => $dateFields,
                    ]);
                }

                $config->push("sources.{$type->name}OrderOptions", [
                    'label' => $group['title'],
                    'options' => $options,
                ]);
            }
        }
    }
}

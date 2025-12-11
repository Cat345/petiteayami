<?php

namespace YOOtheme\Builder\Templates\Listener;

use YOOtheme\Builder\BuilderConfig;

class LoadBuilderConfig
{
    /**
     * @param BuilderConfig $config
     */
    public static function handle($config): void
    {
        $groups = [];
        $singles = [];
        $templates = [];

        foreach ($config['templates'] ?? [] as $name => $template) {
            if (isset($template['group'])) {
                $groups[$template['group']][] = ['value' => $name, 'text' => $template['label']];
            } else {
                $singles[] = ['value' => $name, 'text' => $template['label']];
            }
        }

        foreach ($groups as $name => $group) {
            $templates[] = ['label' => $name, 'options' => $group];
        }

        $config->push('templateOptions', ...$singles, ...$templates);
    }
}

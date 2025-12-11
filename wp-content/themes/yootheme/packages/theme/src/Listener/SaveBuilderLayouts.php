<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Arr;
use YOOtheme\Builder;

class SaveBuilderLayouts
{
    public Builder $builder;

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    public function handle($config): array
    {
        Arr::update(
            $config,
            'footer.config',
            fn($footer) => $footer
                ? $this->builder->withParams(['context' => 'save'])->load(json_encode($footer))
                : null,
        );

        Arr::update($config, 'menu.items', function ($items) {
            foreach ($items ?: [] as $id => $item) {
                if (!empty($item['content'])) {
                    $items[$id]['content'] = $this->builder
                        ->withParams(['context' => 'save'])
                        ->load(json_encode($item['content']));
                }
            }

            return $items;
        });

        return $config;
    }
}

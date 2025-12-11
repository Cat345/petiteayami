<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Builder;
use YOOtheme\Config;

class UpdateBuilderLayouts
{
    public Config $config;
    public Builder $builder;

    public function __construct(Config $config, Builder $builder)
    {
        $this->config = $config;
        $this->builder = $builder;
    }

    public function handle(): void
    {
        $this->config->update(
            '~theme.footer.content',
            fn($footer) => $footer ? $this->builder->load(json_encode($footer)) : null,
        );

        $this->config->update('~theme.menu.items', function ($items) {
            foreach ($items ?: [] as $id => $item) {
                if (!empty($item['content'])) {
                    $items[$id]['content'] = $this->builder->load(json_encode($item['content']));
                }
            }

            return $items;
        });
    }
}

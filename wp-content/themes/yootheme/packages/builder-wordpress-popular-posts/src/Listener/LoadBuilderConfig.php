<?php

namespace YOOtheme\Builder\Wordpress\PopularPosts\Listener;

use WordPressPopularPosts\Helper as PopularPostsHelper;
use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\Wordpress\Source\Helper as SourceHelper;
use function YOOtheme\trans;

class LoadBuilderConfig
{
    /**
     * @param BuilderConfig $config
     */
    public static function handle($config): void
    {
        if (!class_exists(PopularPostsHelper::class)) {
            return;
        }

        foreach (SourceHelper::getPostTypes() as $type) {
            $config->push("sources.{$type->name}OrderOptions", [
                'label' => trans('Popular %post_type%', ['%post_type%' => $type->label]),
                'options' => [
                    ['text' => trans('Comments'), 'value' => 'comments'],
                    ['text' => trans('Total Views'), 'value' => 'views'],
                    ['text' => trans('Average Daily Views'), 'value' => 'avg'],
                ],
            ]);

            $config->push("sources.{$type->name}OrderDirectionOptions", [
                'label' => trans('Popular %post_type%', ['%post_type%' => $type->label]),
                'options' => [
                    ['text' => trans('Last 24 hours'), 'value' => 'daily'],
                    ['text' => trans('Last 7 days'), 'value' => 'weekly'],
                    ['text' => trans('Last 30 days'), 'value' => 'monthly'],
                    ['text' => trans('All-time'), 'value' => 'all'],
                ],
            ]);
        }
    }
}

<?php

namespace YOOtheme\Builder\Wordpress\Source\Listener;

use YOOtheme\Builder\Source;
use YOOtheme\Builder\Source\Type\RequestType;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Builder\Wordpress\Source\Type;
use YOOtheme\Str;

class LoadSourceTypes
{
    /**
     * @param Source $source
     */
    public static function handle($source): void
    {
        $query = [
            [Type\DateQueryType::class, 'config'],
            [Type\UserQueryType::class, 'config'],
            [Type\SearchQueryType::class, 'config'],
            [Type\SiteQueryType::class, 'config'],
        ];

        $types = [
            ['Date', [Type\DateType::class, 'config']],
            ['Request', [RequestType::class, 'config']],
            ['Search', [Type\SearchType::class, 'config']],
            ['Site', [Type\SiteType::class, 'config']],
            ['User', [Type\UserType::class, 'config']],
            ['Attachment', [Type\AttachmentType::class, 'config']],
        ];

        foreach (Helper::getPostTypes() as $type) {
            $query[] = fn() => Type\PostQueryType::config($source, $type);
            $types[] = [Str::camelCase($type->name, true), fn() => Type\PostType::config($type)];
        }

        $types[] = ['MenuItem', [Type\MenuItemType::class, 'config']];

        foreach (Helper::getTaxonomies() as $taxonomy) {
            $query[] = fn() => Type\TaxonomyQueryType::config($source, $taxonomy);
            $types[] = [
                Str::camelCase($taxonomy->name, true),
                fn() => Type\TaxonomyType::config($taxonomy),
            ];
        }

        $query[] = [Type\CustomMenuItemQueryType::class, 'config'];
        $query[] = [Type\CustomUserQueryType::class, 'config'];

        foreach ($query as $args) {
            $source->queryType($args);
        }

        foreach ($types as $args) {
            $source->objectType(...$args);
        }
    }
}

<?php

namespace YOOtheme\Builder\Wordpress\Source\Listener;

use YOOtheme\Builder\Source\Type\RequestType;
use YOOtheme\Builder\Wordpress\Source\Helper;
use YOOtheme\Builder\Wordpress\Source\Type;
use YOOtheme\Str;

class LoadSourceTypes
{
    public static function handle($source): void
    {
        $query = [
            Type\DateQueryType::config(),
            Type\UserQueryType::config(),
            Type\SearchQueryType::config(),
            Type\SiteQueryType::config(),
        ];

        $types = [
            ['Date', Type\DateType::config()],
            ['Request', RequestType::config()],
            ['Search', Type\SearchType::config()],
            ['Site', Type\SiteType::config()],
            ['User', Type\UserType::config()],
            ['Attachment', Type\AttachmentType::config()],
        ];

        foreach (Helper::getPostTypes() as $type) {
            $query[] = Type\PostQueryType::config($source, $type);
            $types[] = [Str::camelCase($type->name, true), Type\PostType::config($type)];
        }

        $types[] = ['MenuItem', Type\MenuItemType::config()];

        foreach (Helper::getTaxonomies() as $taxonomy) {
            $query[] = Type\TaxonomyQueryType::config($source, $taxonomy);
            $types[] = [
                Str::camelCase($taxonomy->name, true),
                Type\TaxonomyType::config($taxonomy),
            ];
        }

        $query[] = Type\CustomMenuItemQueryType::config();
        $query[] = Type\CustomUserQueryType::config();

        foreach ($query as $args) {
            $source->queryType($args);
        }

        foreach ($types as $args) {
            $source->objectType(...$args);
        }
    }
}

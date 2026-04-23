<?php

namespace YOOtheme\Builder\Wordpress\Toolset\Type;

use WP_Post;
use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Toolset\Helper;
use YOOtheme\Str;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class RelationshipFieldsType
{
    /**
     * @param array<string, mixed> $relationships
     *
     * @return ObjectConfig
     */
    public static function config(Source $source, string $name, array $relationships): array
    {
        return [
            'fields' => array_reduce(
                $relationships,
                function ($fields, $relationship) use ($source, $name) {
                    // determine role in relationship
                    $isParent = $relationship['roles']['parent']['types'][0] === $name;

                    $config = [
                        'name' => Str::snakeCase($relationship['slug']),
                        'metadata' => [
                            'label' => $relationship['labels']['singular'],
                            'group' => trans('Relationships'),
                        ],
                        'extensions' => [
                            'call' => [
                                'args' => [
                                    'slug' => $relationship['slug'],
                                    'isParent' => $isParent,
                                ],
                            ],
                        ],
                    ];

                    // find type of related items
                    $type = Helper::getPostType(
                        $relationship['roles'][$isParent ? 'child' : 'parent']['types'][0],
                    );

                    if (!$type) {
                        return $fields;
                    }

                    if (
                        $relationship['cardinality']['type'] === 'one-to-one' ||
                        ($relationship['cardinality']['type'] === 'one-to-many' && !$isParent)
                    ) {
                        $config['type'] = Str::camelCase($type->name, true);
                        $config['extensions']['call']['func'] = __CLASS__ . '::resolve';
                    } else {
                        $relationshipName = Str::camelCase(
                            ['toolset', $type->name, $relationship['slug']],
                            true,
                        );

                        $source->objectType(
                            $relationshipName,
                            fn() => RelationshipType::config($type->name, $relationship),
                        );

                        $config['type'] = ['listOf' => $relationshipName];
                        $config['extensions']['call']['func'] = __CLASS__ . '::resolveManyToMany';
                    }

                    return $fields + [$config['name'] => $config];
                },
                [],
            ),
        ];
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     * @return ?WP_Post
     */
    public static function resolve($post, $args)
    {
        $related = toolset_get_related_post(
            $post,
            $args['slug'],
            $args['isParent'] ? 'child' : 'parent',
        );

        return $related !== 0 ? get_post($related) : null;
    }

    /**
     * @param WP_Post $post
     * @param array<string, mixed> $args
     * @return array<mixed>
     */
    public static function resolveManyToMany($post, $args)
    {
        $isParent = $args['isParent'];

        /**
         * @var list<array{parent?: int, child?: int, intermediary: int}> $roles
         */
        $roles = toolset_get_related_posts($post, $args['slug'], [
            'query_by_role' => $isParent ? 'parent' : 'child',
            'role_to_return' => [$isParent ? 'child' : 'parent', 'intermediary'],
        ]);

        return array_map(
            fn($role) => [
                'post' => $role[$isParent ? 'child' : 'parent'],
                'intermediary' => $role['intermediary'],
            ],
            $roles,
        );
    }
}

<?php

namespace YOOtheme\Builder\Source\Filesystem\Type;

use YOOtheme\Builder\Source\Filesystem\FileHelper;
use function YOOtheme\app;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from \YOOtheme\Builder\Source
 */
class FilesQueryType
{
    /**
     * @return ObjectConfig
     */
    public static function config(string $rootDir): array
    {
        return [
            'fields' => [
                'files' => [
                    'type' => [
                        'listOf' => 'File',
                    ],

                    'args' => [
                        'pattern' => [
                            'type' => 'String',
                        ],
                        'offset' => [
                            'type' => 'Int',
                            'defaultValue' => 0,
                        ],
                        'limit' => [
                            'type' => 'Int',
                            'defaultValue' => 10,
                        ],
                        'order' => [
                            'type' => 'String',
                            'defaultValue' => 'name',
                        ],
                        'order_direction' => [
                            'type' => 'String',
                            'defaultValue' => 'ASC',
                        ],
                    ],

                    'metadata' => [
                        'label' => trans('Files'),
                        'group' => trans('External'),
                        'fields' => [
                            'pattern' => [
                                'label' => trans('Path Pattern'),
                                'description' => "Pick a folder to load file content dynamically. Alternatively, set a path <a href=\"https://www.php.net/manual/en/function.glob.php\" target=\"_blank\">glob pattern</a> to filter files. For example <code>{$rootDir}/*.{jpg,png}</code>. The path is relative to the system folder and has to be a subdirectory of <code>{$rootDir}</code>.",
                                'type' => 'select-file',
                            ],
                            '_offset' => [
                                'description' => trans(
                                    'Set the starting point and limit the number of files.',
                                ),
                                'type' => 'grid',
                                'width' => '1-2',
                                'fields' => [
                                    'offset' => [
                                        'label' => trans('Start'),
                                        'type' => 'number',
                                        'modifier' => 1,
                                        'attrs' => [
                                            'min' => 1,
                                            'required' => true,
                                        ],
                                    ],
                                    'limit' => [
                                        'label' => trans('Quantity'),
                                        'type' => 'limit',
                                        'attrs' => [
                                            'min' => 1,
                                        ],
                                    ],
                                ],
                            ],
                            '_order' => [
                                'type' => 'grid',
                                'width' => '1-2',
                                'description' => trans(
                                    'The Default order will follow the order set by the brackets or fallback to the default files order set by the system.',
                                ),
                                'fields' => [
                                    'order' => [
                                        'label' => trans('Order'),
                                        'type' => 'select',
                                        'options' => [
                                            trans('Default') => 'default',
                                            trans('Alphabetical') => 'name',
                                            trans('Random') => 'rand',
                                        ],
                                    ],
                                    'order_direction' => [
                                        'label' => trans('Direction'),
                                        'type' => 'select',
                                        'options' => [
                                            trans('Ascending') => 'ASC',
                                            trans('Descending') => 'DESC',
                                        ],
                                        'enable' => 'order != "rand"',
                                    ],
                                ],
                            ],
                        ],
                    ],

                    'extensions' => [
                        'call' => __CLASS__ . '::resolve',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param mixed $root
     * @param array<string, mixed> $args
     * @return array<string>
     */
    public static function resolve($root, array $args): array
    {
        return app(FileHelper::class)->query($args);
    }
}

<?php

namespace YOOtheme\Builder\Source\Filesystem\Type;

use YOOtheme\File;
use YOOtheme\Path;
use YOOtheme\Str;
use YOOtheme\Url;
use YOOtheme\View;
use function YOOtheme\app;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from \YOOtheme\Builder\Source
 */
class FileType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
    {
        return [
            'fields' => [
                'name' => [
                    'type' => 'String',
                    'args' => [
                        'title_case' => [
                            'type' => 'Boolean',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Name'),
                        'arguments' => [
                            'title_case' => [
                                'label' => trans('Convert'),
                                'type' => 'checkbox',
                                'text' => trans('Convert to title-case'),
                            ],
                        ],
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::name',
                    ],
                ],

                'basename' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Basename'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::basename',
                    ],
                ],

                'dirname' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Dirname'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::dirname',
                    ],
                ],

                'url' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Url'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::url',
                    ],
                ],

                'path' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Path'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::path',
                    ],
                ],

                'content' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Content'),
                        'filters' => ['limit', 'preserve'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::content',
                    ],
                ],

                'size' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Size'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::size',
                    ],
                ],

                'extension' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Extension'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::extension',
                    ],
                ],

                'mimetype' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Mimetype'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::mimetype',
                    ],
                ],

                'accessed' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Accessed Date'),
                        'filters' => ['date'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::accessed',
                    ],
                ],

                'changed' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Changed Date'),
                        'filters' => ['date'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::changed',
                    ],
                ],

                'modified' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Modified Date'),
                        'filters' => ['date'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::modified',
                    ],
                ],
            ],

            'metadata' => [
                'type' => true,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function name(string $file, array $args): string
    {
        $name = basename($file, Path::extname($file));

        if (!empty($args['title_case'])) {
            $name = Str::titleCase($name);
        }

        return $name;
    }

    public static function content(string $file): ?string
    {
        return File::getContents($file);
    }

    /**
     * @return ?int
     */
    public static function size(string $file)
    {
        return app(View::class)->formatBytes(File::getSize($file) ?: 0);
    }

    /**
     * @return ?int
     */
    public static function accessed(string $file)
    {
        return File::getATime($file);
    }

    /**
     * @return ?int
     */
    public static function changed(string $file)
    {
        return File::getCTime($file);
    }

    /**
     * @return ?int
     */
    public static function modified(string $file)
    {
        return File::getMTime($file);
    }

    /**
     * @return string|false
     */
    public static function mimetype(string $file)
    {
        return File::getMimetype($file);
    }

    public static function extension(string $file): string
    {
        return File::getExtension($file);
    }

    public static function basename(string $file): string
    {
        return basename($file);
    }

    public static function dirname(string $file): string
    {
        return dirname(self::path($file));
    }

    public static function path(string $file): string
    {
        return Path::relative('~', $file);
    }

    public static function url(string $file): string
    {
        return Url::relative(Url::to($file));
    }
}

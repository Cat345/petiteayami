<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use YOOtheme\File;
use YOOtheme\Url;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from \YOOtheme\Builder\Source
 */
class FileFieldType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
    {
        return [
            'fields' => [
                'title' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Title'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::title',
                    ],
                ],

                'alt' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Alt'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::alt',
                    ],
                ],

                'caption' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Caption'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::caption',
                    ],
                ],

                'description' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Description'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::description',
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

                'date' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Date'),
                        'filters' => ['date'],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::date',
                    ],
                ],

                'mimetype' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Mimetype'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::mimeType',
                    ],
                ],

                'icon' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Icon'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::mimeTypeIcon',
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

                'url' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Url'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::url',
                    ],
                ],
            ],
            'metadata' => [
                'type' => true,
            ],
        ];
    }

    /**
     * @param int $attachmentId
     */
    public static function title($attachmentId): string
    {
        return get_the_title($attachmentId);
    }

    /**
     * @param int $attachmentId
     */
    public static function alt($attachmentId): ?string
    {
        return get_post_meta($attachmentId, '_wp_attachment_image_alt', true) ?: null;
    }

    /**
     * @param int $attachmentId
     */
    public static function caption($attachmentId): ?string
    {
        return wp_get_attachment_caption($attachmentId) ?: null;
    }

    /**
     * @param int $attachmentId
     */
    public static function description($attachmentId): string
    {
        return get_the_content(null, false, $attachmentId);
    }

    /**
     * @param int $attachmentId
     */
    public static function basename($attachmentId): ?string
    {
        $file = get_attached_file($attachmentId);
        return $file ? wp_basename($file) : null;
    }

    /**
     * @param int $attachmentId
     */
    public static function extension($attachmentId): ?string
    {
        $file = get_attached_file($attachmentId);
        return $file ? File::getExtension($file) : null;
    }

    /**
     * @param int $attachmentId
     */
    public static function date($attachmentId): ?string
    {
        return get_the_date(DATE_W3C, $attachmentId) ?: null;
    }

    /**
     * @param int $attachmentId
     */
    public static function mimeType($attachmentId): ?string
    {
        return get_post_mime_type($attachmentId) ?: null;
    }

    /**
     * @param int $attachmentId
     */
    public static function mimeTypeIcon($attachmentId): ?string
    {
        return wp_mime_type_icon($attachmentId) ?: null;
    }

    /**
     * @param int $attachmentId
     */
    public static function size($attachmentId): ?string
    {
        $file = get_attached_file($attachmentId);

        return $file && File::exists($file) ? (string) size_format(File::getSize($file)) : null;
    }

    /**
     * @param int $attachmentId
     */
    public static function url($attachmentId): ?string
    {
        $url = set_url_scheme(wp_get_attachment_url($attachmentId), 'relative');
        return $url ? Url::relative($url) : null;
    }
}

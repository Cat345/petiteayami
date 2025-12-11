<?php

namespace YOOtheme\Builder\Wordpress\Acf\Type;

use YOOtheme\File;
use function YOOtheme\trans;
use YOOtheme\Url;

class FileFieldType
{
    /**
     * @return array
     */
    public static function config()
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
        ];
    }

    public static function title($attachmentId)
    {
        return get_the_title($attachmentId);
    }

    public static function alt($attachmentId)
    {
        return get_post_meta($attachmentId, '_wp_attachment_image_alt', true) ?: null;
    }

    public static function caption($attachmentId)
    {
        return wp_get_attachment_caption($attachmentId);
    }

    public static function description($attachmentId)
    {
        return get_the_content(null, false, $attachmentId);
    }

    public static function basename($attachmentId)
    {
        return wp_basename(get_attached_file($attachmentId) ?: '');
    }

    public static function date($attachmentId)
    {
        return get_the_date(DATE_W3C, $attachmentId);
    }

    public static function mimeType($attachmentId)
    {
        return get_post_mime_type($attachmentId);
    }

    public static function mimeTypeIcon($attachmentId)
    {
        return wp_mime_type_icon($attachmentId);
    }

    public static function size($attachmentId)
    {
        $file = get_attached_file($attachmentId);
        if ($file && File::exists($file)) {
            return size_format(File::getSize($file));
        }
    }

    public static function url($attachmentId)
    {
        $url = set_url_scheme(wp_get_attachment_url($attachmentId), 'relative');
        return $url ? Url::relative($url) : $url;
    }
}

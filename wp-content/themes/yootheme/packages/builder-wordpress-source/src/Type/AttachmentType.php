<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use function YOOtheme\trans;
use YOOtheme\Url;

class AttachmentType
{
    /**
     * @return array
     */
    public static function config()
    {
        return [
            'fields' => [
                'url' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Url'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::url',
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
            ],
        ];
    }

    public static function caption($attachmentId)
    {
        return wp_get_attachment_caption($attachmentId);
    }

    public static function alt($attachmentId)
    {
        return get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
    }

    public static function url($attachmentId)
    {
        $url = set_url_scheme(wp_get_attachment_url($attachmentId), 'relative');
        return $url ? Url::relative($url) : $url;
    }
}

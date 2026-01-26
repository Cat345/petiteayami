<?php

namespace YOOtheme\Builder\Wordpress\Source\Type;

use YOOtheme\Builder\Source;
use YOOtheme\Url;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class AttachmentType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
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

    /**
     * @param int $attachmentId
     */
    public static function caption($attachmentId): ?string
    {
        return wp_get_attachment_caption($attachmentId) ?: null;
    }

    /**
     * @param int $attachmentId
     *
     * @return ?string
     */
    public static function alt($attachmentId)
    {
        return get_post_meta($attachmentId, '_wp_attachment_image_alt', true) ?: null;
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

<?php

namespace YOOtheme;

use YOOtheme\Image\Listener;

return [
    'routes' => [
        [
            ['get', 'head'],
            '/cache/{file:.+}',
            ImageController::class . '@get',
            ['allowed' => true, 'save' => true],
        ],
    ],

    'events' => [
        'image.create' => [
            Listener\LoadExifData::class => 'handle',
            Listener\CreateImageQuery::class => 'handle',
            Listener\CreateImageSvg::class => 'handle',
            Listener\CreateImageGif::class => 'handle',
            Listener\CreateImageStockPhoto::class => 'handle',
            Listener\CreateImageYouTube::class => 'handle',
            Listener\CreateImageVimeo::class => 'handle',
        ],

        'image.resolve' => [
            Listener\CreateImageVimeo::class => 'resolve',
        ],

        'html.image' => [
            Listener\ParseFocalPoint::class => ['handle', 30],
            Listener\LoadImageElement::class => ['handle', 25],
            Listener\LoadImageSize::class => ['handle', 20],
            Listener\LoadImageCover::class => ['handle', 15],
            Listener\LoadThumbnail::class => ['handle', 10],
            Listener\LoadSourceSet::class => ['handle', 5],
            Listener\LoadImageStyle::class => 'handle',
            Listener\LoadImageGif::class => 'handle',
            Listener\LoadImageSvg::class => 'handle',
            Listener\LoadImageLazy::class => 'handle',
        ],

        'html.bgImage' => [
            Listener\ParseFocalPoint::class => ['handle', 30],
            Listener\LoadBackgroundImageElement::class => ['handle', 25],
            Listener\LoadImageSize::class => ['handle', 20],
            Listener\LoadThumbnail::class => ['handle', 15],
            Listener\LoadBackgroundImageCover::class => ['handle', 10],
            Listener\LoadSourceSet::class => ['handle', 5],
            Listener\LoadBackgroundImageStyle::class => 'handle',
        ],

        'url.resolve' => [Listener\LoadImageUrl::class => ['resolve', 10]],
    ],
];

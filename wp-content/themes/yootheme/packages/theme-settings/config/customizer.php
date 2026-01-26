<?php

return [
    'sections' => [
        'settings' => [
            'title' => 'Settings',
            'priority' => 60,
            'fields' => [
                'settings' => [
                    'type' => 'menu',
                    'items' => [
                        'favicon' => 'Favicon',
                        'css' => 'CSS',
                        'scripts' => 'Scripts',
                        'consent' => 'Consent Manager',
                        'external-services' => 'External Services',
                        'api-key' => 'API Key',
                        'advanced' => 'Advanced',
                        'systemcheck' => 'System Check',
                        'about' => 'About',
                    ],
                ],
            ],
        ],
    ],
    'panels' => [
        'favicon' => [
            'title' => 'Favicon',
            'width' => 400,
            'fields' => [
                'favicon' => [
                    'label' => 'Favicon PNG',
                    'description' =>
                        'Select your <code>favicon.png</code>. It appears in the browser\'s address bar, tab and bookmarks. The recommended size is 96x96 pixels.',
                    'type' => 'image',
                    'mediapicker' => [
                        'photos' => false,
                    ],
                ],
                'favicon_svg' => [
                    'label' => 'Favicon SVG',
                    'description' =>
                        'Select an optional <code>favicon.svg</code>. Modern browsers will use it instead of the PNG image. Use CSS to toggle the SVG color scheme for light/dark mode.',
                    'type' => 'image',
                    'mediapicker' => [
                        'photos' => false,
                    ],
                ],
                'touchicon' => [
                    'label' => 'Touch Icon',
                    'description' =>
                        'Select your <code>apple-touch-icon.png</code>. It appears when the website is added to the home screen on iOS devices. The recommended size is 180x180 pixels.',
                    'type' => 'image',
                    'mediapicker' => [
                        'photos' => false,
                    ],
                ],
            ],
        ],
        'css' => [
            'title' => 'CSS',
            'width' => 500,
            'fields' => [
                'custom_less' => [
                    'description' =>
                        'Add custom CSS or Less to your site. All Less theme variables and mixins are available. Don\'t use any <code>&lt;style&gt;</code> tag.',
                    'type' => 'editor',
                    'editor' => 'code',
                    'mode' => 'text/x-less',
                    'attrs' => [
                        'id' => 'custom_less',
                        'debounce' => 1000,
                    ],
                ],
            ],
        ],
        'scripts' => [
            'title' => 'Scripts',
            'fields' => [
                'scripts' => [
                    'type' => 'item-panel',
                    'options' => [
                        ['text' => 'Custom Script', 'value' => 'script-custom'],
                        [
                            'label' => 'Prebuilt scripts',
                            'options' => [[
                                'evaluate' => 'yootheme.customizer.script.types',
                            ]]
                        ]
                    ],
                    'prop' => 'type',
                    'title' => 'service_title',
                    'button' => 'Add Script',
                    'panel' => ['width' => 500],
                ],
            ],
        ],
        'external-services' => [
            'title' => 'External Services',
            'width' => 400,
            'fields' => [
                'mailchimp_api' => [
                    'label' => 'Mailchimp API Token',
                    'description' =>
                        'Enter your <a href="https://kb.mailchimp.com/integrations/api-integrations/about-api-keys" target="_blank">Mailchimp</a> API key for using it with the Newsletter element.',
                ],
                'cmonitor_api' => [
                    'label' => 'Campaign Monitor API Token',
                    'description' =>
                        'Enter your <a href="https://help.campaignmonitor.com/topic.aspx?t=206" target="_blank">Campaign Monitor</a> API key for using it with the Newsletter element.',
                ],
            ],
        ],
        'api-key' => [
            'title' => 'API Key',
            'width' => 400,
            'fields' => [
                'yootheme_apikey' => [
                    'label' => 'YOOtheme API Key',
                    'description' =>
                        'Enter the API key to enable 1-click updates for YOOtheme Pro and to access the layout library as well as the Unsplash image library. You can create an API Key for this website in your <a href="https://yootheme.com/shop/my-account/websites/" target="_blank">Account settings</a>.',
                    'show' => 'yootheme_apikey !== false',
                ],
                'yootheme_apikey_warning' => [
                    'label' => 'YOOtheme API Key',
                    'description' =>
                        'Please install/enable the <a href="index.php?option=com_plugins&view=plugins&filter_search=installer%20yootheme">installer plugin</a> to enable this feature.',
                    'type' => 'description',
                    'show' => 'yootheme_apikey === false',
                ],
            ],
        ],
        'advanced' => [
            'title' => 'Advanced',
            'width' => 400,
            'fields' => [
                'webp' => [
                    'label' => 'Next-Gen Images',
                    'text' => 'Serve WebP images',
                    'type' => 'checkbox',
                ],
                'avif' => [
                    'description' =>
                        'Serve optimized image formats with better compression and quality than JPEG and PNG.',
                    'text' => 'Serve AVIF images',
                    'type' => 'checkbox',
                ],
                '_image_quality' => [
                    'type' => 'button-panel',
                    'panel' => 'image-quality',
                    'text' => 'Edit Image Quality',
                ],
                'image_urls' => [
                    'label' => 'Image URLs',
                    'text' => 'Enable cache-friendly URLs',
                    'description' => 'Use the same image URLs before and after cached images are generated to support page caching.',
                    'type' => 'checkbox',
                ],
                'highlight' => [
                    'label' => 'Syntax Highlighting',
                    'description' =>
                        'Select the style for the code syntax highlighting. Use GitHub for light and Monokai for dark backgrounds.',
                    'type' => 'select',
                    'options' => [
                        'None' => '',
                        'GitHub (Light)' => 'github',
                        'Monokai (Dark)' => 'monokai',
                    ],
                ],
                'clear_cache' => [
                    'label' => 'Cache',
                    'description' =>
                        'Remove all cached files to regenerate them.',
                    'type' => 'cache',
                ],
                '_config' => [
                    'label' => 'Theme Settings',
                    'description' =>
                        'Export all theme settings and import them into another installation. This doesn\'t include content from the layout, style and element libraries or the template builder.',
                    'type' => 'config',
                ],
            ],
        ],
        'systemcheck' => [
            'title' => 'System Check',
            'width' => 400,
        ],
        'about' => [
            'title' => 'About',
            'width' => 400,
        ],
        'image-quality' => [
            'title' => 'Image Quality',
            'width' => 400,
            'fields' => [
                '_image_quality_description' => [
                    'description' =>
                        'Define the image quality in percent for generated JPG images and when converting JPEG and PNG to next-gen image formats.<br><br>Mind that setting the image quality too high will have a negative impact on page loading times.',
                    'type' => 'description',
                ],
                'image_quality_jpg' => [
                    'label' => 'JPEG',
                    'attrs' => [
                        'placeholder' => '80',
                    ],
                ],
                'image_quality_png_webp' => [
                    'label' => 'PNG to WebP',
                    'attrs' => [
                        'placeholder' => '100',
                    ],
                ],
                'image_quality_jpg_webp' => [
                    'label' => 'JPEG to WebP',
                    'attrs' => [
                        'placeholder' => '85',
                    ],
                ],
                'image_quality_png_avif' => [
                    'label' => 'PNG to AVIF',
                    'attrs' => [
                        'placeholder' => '85',
                    ],
                ],
                'image_quality_jpg_avif' => [
                    'label' => 'JPEG to AVIF',
                    'attrs' => [
                        'placeholder' => '75',
                    ],
                ],
            ],
        ],
        'script-custom' => [
            'fields' => [
                'service_title' => [
                    'label' => 'Title',
                ],
                'head' => [
                    'label' => 'Head Scripts',
                    'description' => 'The <code>&lt;script&gt;</code> tag is mandatory.',
                    'type' => 'editor',
                    'editor' => 'code',
                ],
                'body' => [
                    'label' => 'Body Scripts',
                    'description' => 'The <code>&lt;script&gt;</code> tag is mandatory.',
                    'type' => 'editor',
                    'editor' => 'code',
                ],
                'category' => [
                    'label' => 'Consent Category',
                    'type' => 'select',
                    'defaultIndex' => 0,
                    'options' => [
                        'Functional' => 'functional',
                        'Preferences' => 'preferences',
                        'Statistics' => 'statistics',
                        'Marketing' => 'marketing',
                    ],
                    'description' => 'Functional scripts are always loaded. Optionally, set the category that must be accepted in the consent manager before the script is loaded.',
                ],
                'service' => [
                    'label' => 'Consent Service ID',
                    'description' => 'Optionally, allow this service to be individually accepted in the consent manager by setting a unique name for the service, e.g. <code>my-service</code>.',
                ],
                'status' => [
                    'label' => 'Status',
                    'description' => 'Disable the script and publish it later.',
                    'type' => 'checkbox',
                    'text' => 'Disable script',
                    'attrs' => [
                        'true-value' => 'disabled',
                        'false-value' => '',
                    ],
                ],
            ],
        ],
    ],
    'script' => [
        'types' => [],
    ],
];

<?php

namespace YOOtheme;

use YOOtheme\Http\HttpFactory;
use YOOtheme\Http\Request;
use YOOtheme\Wordpress\FilterLoader;
use YOOtheme\Wordpress\Platform;
use YOOtheme\Wordpress\Router;

// use realpath to resolve symlink
$rootDir = dirname(realpath(WP_CONTENT_DIR));

// use content parent directory as root
Url::setBase(dirname(content_url()));
Path::setAlias('~', strtr($rootDir, '\\', '/'));

return [
    'config' => function () use ($rootDir) {
        global $wp_version;

        return [
            'app' => [
                'platform' => 'wordpress',
                'version' => $wp_version,
                'secret' => NONCE_KEY,
                'debug' => WP_DEBUG,
                'rootDir' => strtr($rootDir, '\\', '/'),
                'tempDir' => strtr(get_temp_dir(), '\\', '/'),
                'adminDir' => Path::resolve(ABSPATH, 'wp-admin'),
                'pluginDir' => strtr(WP_PLUGIN_DIR, '\\', '/'),
                'contentDir' => strtr(WP_CONTENT_DIR, '\\', '/'),
                'uploadDir' => strtr(wp_get_upload_dir()['basedir'], '\\', '/'),
                'isSite' => !is_admin(),
                'isAdmin' => is_admin(),
                'sef' => (bool) get_option('permalink_structure'),
            ],

            // TODO
            'req' => [
                'baseUrl' => home_url('', 'relative'),
                'rootUrl' => home_url('', 'relative'),
                'siteUrl' => site_url(),
            ],

            'locale' => [
                'rtl' => is_rtl(),
                'code' => determine_locale(),
                'timezone' => wp_timezone_string(),
            ],

            'session' => [
                'token' => wp_create_nonce(),
            ],
        ];
    },

    'events' => [
        'url.route' => [Router::class => 'generate'],
        'app.error' => [Platform::class => 'handleError'],
    ],

    'actions' => [
        'init' => [Router::class => 'rewrite'],
        'parse_request' => [Router::class => 'parse'],
        'wp_ajax_yootheme' => [Platform::class => 'handleRoute'],
        'wp_ajax_nopriv_yootheme' => [Platform::class => 'handleRoute'],
        'wp_footer' => [Platform::class => 'printFooterScripts'],
        'wp_head' => [Platform::class => [['printStyles', 8], ['printScripts', 20]]],
        'admin_print_scripts' => [Platform::class => [['printStyles', 8], ['printScripts', 21]]],
        'admin_print_footer_scripts' => [Platform::class => 'printFooterScripts'],
    ],

    'filters' => [
        'query_vars' => [Router::class => 'addQueryVars'],
    ],

    'loaders' => [
        'filters' => FilterLoader::class,
        'actions' => FilterLoader::class,
    ],

    'services' => [
        CsrfMiddleware::class => fn(Config $config) => new CsrfMiddleware(
            $config('session.token'),
            'wp_verify_nonce',
        ),

        Request::class => fn(Config $config) => (new HttpFactory())->createServerRequestFromGlobals(
            $config('req.href'),
            stripslashes_deep($_GET),
            stripslashes_deep($_POST),

            // Ensure $_FILES has not been unset
            // https://wordpress.org/plugins/acf-extended/
            empty($_FILES) ? [] : $_FILES,
            stripslashes_deep($_COOKIE),
            stripslashes_deep($_SERVER),
        ),

        Storage::class => Wordpress\Storage::class,

        HttpClientInterface::class => Wordpress\HttpClient::class,
    ],
];

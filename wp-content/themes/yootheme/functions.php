<?php

/**
 * Boostrap theme.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions
 */
$app = require __DIR__ . '/bootstrap.php';
$app->load(
    __DIR__ .
    '/{packages/{platform-wordpress,' .
    'theme{,-analytics,-cookie,-highlight,-settings},' .
    'builder{,-source*,-templates,-newsletter},' .
    'styler,theme-wordpress*,builder-wordpress*}' .
    '/bootstrap.php,config.php}'
);

/**
 * Added for backwards compatibility to support pre 6.0.0 WordPress versions.
 */
if (!function_exists('wp_get_list_item_separator')) {
    function wp_get_list_item_separator()
    {
        return ', ';
    }
}

/**
 * Try to remove `wp-content/install.php` which wasn't removed during demo installation.
 */
add_action('admin_notices', function () {
    if (!is_file($file = WP_CONTENT_DIR . '/install.php')) {
        return;
    }

    $contents = @file_get_contents($file, false, null, 0, 500) ?: '';

    if (strpos($contents, 'shutdown') && !@unlink($file)) {
        printf(
            '<div class="notice notice-warning"><h2>%s</h2><p>%s</p></div>',
            'Action required: Critical vulnerability in your installation',
            'YOOtheme Pro was unable to remove the <code>wp-content/install.php</code> file. This file was used during the installation of the YOOtheme Pro demo package. It can potentially be used to reset the database. Please delete the file manually.'
        );
    }
});

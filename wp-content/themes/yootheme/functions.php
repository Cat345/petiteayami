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
        'theme{,-consent,-highlight,-settings},' .
        'builder{,-source*,-templates,-newsletter},' .
        'styler,theme-wordpress*,builder-wordpress*}' .
        '/bootstrap.php,config.php}',
);

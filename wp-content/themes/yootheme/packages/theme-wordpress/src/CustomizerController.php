<?php

namespace YOOtheme\Theme\Wordpress;

use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\Http\Request;
use YOOtheme\Path;

class CustomizerController
{
    public static function index(Config $config, Request $request)
    {
        // init customizer
        Event::emit('customizer.init');

        // init config
        $config->add('customizer', [
            'site' => trailingslashit(home_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'config' => $config('~theme'),
            'return' => $request->getQueryParam('return') ?: get_admin_url(),
        ]);

        // load template
        include Path::get('../templates/customizer.php');
    }

    public static function save(Request $request)
    {
        $request->abortIf(
            !current_user_can('edit_theme_options'),
            403,
            'Insufficient User Rights.',
        );

        // get theme config
        $values = Event::emit('config.save|filter', $request->getParam('config', []));

        // save theme config
        set_theme_mod('config', json_encode($values, JSON_UNESCAPED_SLASHES));

        return 'success';
    }
}

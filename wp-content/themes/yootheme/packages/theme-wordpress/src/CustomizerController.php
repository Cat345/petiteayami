<?php

namespace YOOtheme\Theme\Wordpress;

use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\Http\Request;
use YOOtheme\Path;
use YOOtheme\View;

class CustomizerController
{
    public static function index(Config $config, Request $request): string
    {
        // init customizer
        Event::emit('customizer.init');

        $return = $request->getQueryParam('return');
        $return = is_string($return) ? $return : '';

        // init config
        $config->add('customizer', [
            'site' => trailingslashit(home_url()),
            'config' => $config('~theme'),
            'return' =>
                wp_validate_redirect(wp_sanitize_redirect($return), admin_url()) ?: admin_url(),
        ]);

        // load template
        return (new View())->render(Path::join(__DIR__, '../templates/customizer.php'));
    }

    public static function save(Request $request): string
    {
        $request->abortIf(
            !current_user_can('edit_theme_options'),
            403,
            'Insufficient User Rights.',
        );

        // get theme config
        $values = $request->getParam('config');

        $request->abortIf(!$values || !is_array($values), 400, 'Invalid configuration.');

        $values = Event::emit('config.save|filter', $values);

        $request->abortIf(!$values || !is_array($values), 400, 'Invalid configuration.');

        $encoded = json_encode($values, JSON_UNESCAPED_SLASHES);

        $request->abortIf($encoded === false, 400, 'Invalid configuration.');

        // save theme config
        set_theme_mod('config', $encoded);

        return 'success';
    }
}

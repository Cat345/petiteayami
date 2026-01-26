<?php

namespace YOOtheme\Theme\Consent\Listener;

use YOOtheme\Theme\ThemeConfig;

class SkipDisabledScripts
{
    /**
     * @param ThemeConfig $theme
     */
    public static function config($theme): void
    {
        foreach ($theme->scripts as $key => $script) {
            if (($script['status'] ?? '') === 'disabled') {
                unset($theme->scripts[$key]);
            }
        }
    }
}

<?php

namespace YOOtheme\Theme\Wordpress\Listener;

use YOOtheme\Config;

class LoadCustomScript
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Prints scripts or data in the head tag on the front end.
     *
     * @link https://developer.wordpress.org/reference/hooks/wp_head/
     */
    public function handle(): void
    {
        if ($script = $this->config->get('~theme.custom_js', '')) {
            $script = trim($script);

            // Check for </script> for backwards compatibility (Will be dropped in the future)
            if (!str_starts_with($script, '<') || str_starts_with($script, '</script>')) {
                $attrs = $this->config->get('app.isCustomizer') ? ' data-preview="diff"' : '';
                $script = "<script{$attrs}>{$script}</script>";
            }

            echo $script;
        }
    }
}

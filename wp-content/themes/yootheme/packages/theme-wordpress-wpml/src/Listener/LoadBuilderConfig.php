<?php

namespace YOOtheme\Theme\Wordpress\WPML\Listener;

class LoadBuilderConfig
{
    public static function handle($config): void
    {
        if (!class_exists('SitePress', false)) {
            return;
        }

        // set custom WPMl languages
        if ($languages = apply_filters('wpml_active_languages', null)) {
            $config->set(
                'languages',
                array_values(
                    array_map(
                        fn($lang) => [
                            'text' => $lang['native_name'],
                            'value' => $lang['default_locale'],
                        ],
                        $languages,
                    ),
                ),
            );
        }
    }

    public static function addFilters(): void
    {
        if (!class_exists('SitePress', false)) {
            return;
        }

        if ($nav_menu = static::getNavMenu()) {
            add_filter('get_terms', [$nav_menu, 'get_terms_filter'], 1, 3);
            add_filter('wpml_disable_term_adjust_id', [static::class, 'adjustTermId']);
        }
    }

    /**
     * Disable WPML adjust term id.
     */
    public static function adjustTermId(): bool
    {
        return true;
    }

    /**
     * Get "WPML_Nav_Menu" instance from filters.
     */
    protected static function getNavMenu(): ?\WPML_Nav_Menu
    {
        global $wp_filter;

        foreach ($wp_filter['wp_get_nav_menus'] ?? [] as $filters) {
            foreach ($filters as ['function' => $function]) {
                if (($function[0] ?? null) instanceof \WPML_Nav_Menu) {
                    return $function[0];
                }
            }
        }

        return null;
    }
}

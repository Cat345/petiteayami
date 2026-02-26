<?php

namespace YOOtheme;

return [
    '5.0.15' => function ($config) {
        $rename = [
            'script-google-tagmanager' => 'script-google-analytics',
            'script-maps-leaflet' => 'script-maps-openstreetmap',
        ];

        foreach ($config['scripts'] ?? [] as $key => $script) {
            foreach ($rename as $from => $to) {
                if ($script['type'] === $from) {
                    $config['scripts'][$key]['type'] = $to;
                }
            }
        }

        return $config;
    },

    '5.0.0-beta.5' => function ($config) {
        $key = $config['google_maps'] ?? '';
        unset($config['google_maps']);

        $scripts = [
            $key
                ? ['type' => 'script-maps-google-maps', 'options' => ['apiKey' => $key]]
                : ['type' => 'script-maps-openstreetmap'],
        ];

        $script = trim($config['custom_js'] ?? '');
        unset($config['custom_js']);

        if ($script) {
            // Check for </script> for backwards compatibility
            if (!str_starts_with($script, '<') || str_starts_with($script, '</script>')) {
                $script = "<script>{$script}</script>";
            }

            $scripts[] = ['type' => 'script-custom', 'name' => 'Custom Code', 'head' => $script];
        }

        if ($analytics = $config['google_analytics'] ?? '') {
            $scripts[] = ['type' => 'script-google-tagmanager', 'api_key' => $analytics];
        }
        unset($config['google_analytics'], $config['google_analytics_anonymize']);

        if ($custom_js = Arr::get($config, 'cookie.custom_js')) {
            $scripts[] = [
                'type' => 'script-custom',
                'name' => 'Custom Cookie',
                'category' => 'marketing',
                'head' => "<script>{$custom_js}</script>",
            ];
        }

        $config['scripts'] = $scripts;

        if (Arr::get($config, 'cookie.mode')) {
            Arr::set($config, 'consent.type', 'optin');
        }

        if (Arr::get($config, 'cookie.type') === 'bar') {
            Arr::set($config, 'consent.banner_layout', 'section-' . Arr::get($config, 'cookie.bar_position'));
        } elseif (Arr::get($config, 'cookie.type') === 'notification') {
            Arr::set($config, 'consent.banner_layout', 'notification-' . Arr::get($config, 'cookie.notification_position'));
        }

        if (in_array(Arr::get($config, 'cookie.button_consent_style'), ['', 'icon'])) {
            Arr::set($config, 'cookie.button_consent_style', 'primary');
        }

        Arr::updateKeys($config, [
            'cookie.notification_style' => 'consent.notification_style',
            'cookie.bar_style' => 'consent.section_style',
            'cookie.button_consent_style' => 'consent.button_accept_style',
            'cookie.button_reject_style' => 'consent.button_reject_style',
        ]);

        Arr::del($config, 'cookie');

        return $config;
    },
];

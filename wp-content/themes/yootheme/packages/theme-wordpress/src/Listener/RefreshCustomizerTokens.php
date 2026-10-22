<?php

namespace YOOtheme\Theme\Wordpress\Listener;

class RefreshCustomizerTokens
{
    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function handle(array $response, array $data): array
    {
        if (empty($data['yootheme-customizer-tokens']) || !current_user_can('edit_theme_options')) {
            return $response;
        }

        $response['yootheme-customizer-tokens'] = [
            'csrf' => wp_create_nonce(),
            'session' => wp_create_nonce('yootheme_session'),
        ];

        return $response;
    }
}

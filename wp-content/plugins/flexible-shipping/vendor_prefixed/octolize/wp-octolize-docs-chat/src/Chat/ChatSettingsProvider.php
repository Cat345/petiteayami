<?php

declare (strict_types=1);
namespace FSVendor\Octolize\Docs\Chat;

/**
 * Provides plugin-specific chat settings.
 */
interface ChatSettingsProvider
{
    /**
     * @param array<string, mixed> $settings     Current chat settings.
     * @param array<string, mixed> $request_data Request data.
     *
     * @return array<string, mixed>
     */
    public function prepare_settings(array $settings, array $request_data): array;
}

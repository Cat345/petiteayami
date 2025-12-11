<?php

namespace YOOtheme\Wordpress;

class ThemeUpdate
{
    /**
     * Theme instance.
     */
    protected \WP_Theme $theme;

    /**
     * Update URL.
     */
    protected string $url;

    /**
     * Package URL query parameters.
     */
    protected array $query = [];

    /**
     * Release stability.
     */
    protected string $stability = 'stable';

    /**
     * Cache expiration in seconds.
     */
    protected int $expiration = HOUR_IN_SECONDS;

    /**
     * Constructor.
     */
    public function __construct(\WP_Theme $theme)
    {
        $this->url = $theme->get('UpdateURI');
        $this->theme = $theme;

        // @link https://developer.wordpress.org/reference/hooks/upgrader_package_options/
        add_filter('upgrader_package_options', [$this, 'addQuery']);

        // @link https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5/
        add_filter('pre_set_site_transient_update_themes', [$this, 'checkUpdate']);
    }

    /**
     * Set the update API URL.
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Set the package URL query parameters.
     */
    public function setQuery(array $query): self
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Set the release stability.
     */
    public function setStability(string $stability): self
    {
        $this->stability = $stability;

        return $this;
    }

    /**
     * Set the cache expiration in seconds.
     */
    public function setExpiration(int $expiration): self
    {
        $this->expiration = $expiration;

        return $this;
    }

    /**
     * Add query parameters to package URL.
     */
    public function addQuery($options)
    {
        $query = array_filter($this->query);
        $theme = $options['hook_extra']['theme'] ?? null;

        if ($query && $theme === $this->theme->get_template()) {
            $options['package'] = add_query_arg($query, $options['package']);
        }

        return $options;
    }

    /**
     * Check API for updates.
     */
    public function checkUpdate($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        $theme = $this->theme->get_template();
        $release = $this->getRelease($this->stability);

        if ($release && $this->hasUpdate($release)) {
            $transient->response[$theme] = $release;
            unset($transient->no_update[$theme]);
        } else {
            $transient->no_update[$theme] = $this->mapData();
            unset($transient->response[$theme]);
        }

        return $transient;
    }

    /**
     * Check if an update is available.
     */
    protected function hasUpdate(array $release): bool
    {
        return version_compare($release['new_version'], $this->theme->get('Version'), '>');
    }

    /**
     * Fetch details on the latest updates.
     */
    protected function fetchUpdate(string $url): array
    {
        $theme = $this->theme->get_template();
        $transient = "{$theme}_theme_update";

        if (!is_array($data = get_transient($transient))) {
            $res = wp_remote_get($url);
            $body = wp_remote_retrieve_body($res);
            $code = wp_remote_retrieve_response_code($res);

            if ($code === 200 && ($data = json_decode($body, true))) {
                set_transient($transient, $data, $this->expiration);
            } elseif ($code && $code !== 408) {
                set_transient($transient, [], DAY_IN_SECONDS);
            }
        }

        return $data ?: [];
    }

    /**
     * Get the latest release with preferred stability.
     */
    protected function getRelease(string $stability): ?array
    {
        $update = $this->fetchUpdate($this->url);
        $versions = $update['versions'] ?? [];

        // normalize update data
        $releases = array_map([$this, 'mapData'], $versions);
        $stabilities = array_unique(['stable', $stability]);

        // sort releases, the newest version first
        usort($releases, fn($a, $b) => version_compare($a['new_version'], $b['new_version']) * -1);

        // get the latest release with preferred stability
        foreach ($releases as $release) {
            if (in_array($release['stability'], $stabilities, true)) {
                return $release;
            }
        }

        return null;
    }

    /**
     * Normalize data from the API to the expected values.
     */
    protected function mapData(array $data = []): array
    {
        return [
            'theme' => $this->theme->get_template(),
            'new_version' => strval($data['version'] ?? $this->theme->get('Version')),
            'url' => strval($data['url'] ?? $this->theme->get('ThemeURI')),
            'package' => $data['package'] ?? '',
            'stability' => $data['stability'] ?? '',
            'requires' => strval($data['requires'] ?? $this->theme->get('RequiresWP')),
            'requires_php' => strval($data['requires_php'] ?? $this->theme->get('RequiresPHP')),
        ];
    }
}

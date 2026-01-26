<?php

namespace YOOtheme\Theme;

class Updater
{
    public string $version;

    /**
     * @var list<string>
     */
    public array $updates = [];

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    /**
     * Add update files.
     */
    public function add(string $file): void
    {
        $this->updates[] = $file;
    }

    /**
     * Updates a config.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function update(array $config, array $params): array
    {
        $version = empty($config['version']) ? '1.0.0' : $config['version'];

        // check node version
        if (version_compare($version, $this->version, '>=')) {
            return $config;
        }

        $config['version'] = $this->version;

        // apply update callbacks
        foreach ($this->resolveUpdates($version) as $updates) {
            foreach ($updates as $update) {
                $config = $update($config, $params);
            }
        }

        return $config;
    }

    /**
     * Resolves updates for the current version.
     *
     * @return array<string, list<callable>>
     */
    protected function resolveUpdates(string $version): array
    {
        $resolved = [];

        foreach ($this->updates as $file) {
            $updates = require $file;

            foreach ($updates as $ver => $update) {
                if (version_compare($ver, $version, '>') && is_callable($update)) {
                    $resolved[$ver][] = $update;
                }
            }
        }

        uksort($resolved, 'version_compare');

        return $resolved;
    }
}

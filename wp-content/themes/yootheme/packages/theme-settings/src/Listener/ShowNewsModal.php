<?php

namespace YOOtheme\Theme\Listener;

use YOOtheme\Config;
use YOOtheme\Path;
use YOOtheme\Storage;

class ShowNewsModal
{
    public const KEY = 'news';

    public Config $config;
    public Storage $storage;

    public function __construct(Config $config, Storage $storage)
    {
        $this->config = $config;
        $this->storage = $storage;
    }

    public function handle(): void
    {
        $hash = hash_file('crc32b', Path::get('~theme/NEWS.md'));

        if ($this->storage->get(static::KEY) !== $hash) {
            $this->storage->set(static::KEY, $hash);
            $this->config->set('customizer.news', true);
        }
    }
}

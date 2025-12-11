<?php

namespace YOOtheme\Builder\Source\Filesystem\Listener;

use YOOtheme\Builder\Source\Filesystem\Type;
use YOOtheme\Config;
use YOOtheme\Path;

class LoadSourceTypes
{
    public Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function handle($source): void
    {
        try {
            $rootDir = Path::relative(
                $this->config->get('app.rootDir'),
                $this->config->get('app.uploadDir'),
            );

            $source->queryType(Type\FileQueryType::config($rootDir));
            $source->queryType(Type\FilesQueryType::config($rootDir));
            $source->objectType('File', Type\FileType::config());
        } catch (\Exception $e) {
        }
    }
}

<?php

namespace YOOtheme\Builder\Source\Listener;

use YOOtheme\Builder\Source;
use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\File;
use YOOtheme\GraphQL\Error\Error;
use YOOtheme\Http\Request;

class LoadSourceSchema
{
    public Config $config;
    public Request $request;

    public function __construct(Config $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * @param Source $source
     * @return bool|null
     */
    public function handle($source): ?bool
    {
        $dir = $this->config->get('image.cacheDir');
        $name = "schema-{$this->config->get('source.id')}";
        $file = "{$dir}/{$name}.gql";

        try {
            if (
                $this->config->get('app.isSite') &&
                !$this->request->getAttribute('customizer') &&
                is_file($file) &&
                filectime($file) > filectime(__FILE__)
            ) {
                // load schema from cache
                $hash = hash('crc32b', $file);
                $source->setSchema($source->loadSchema($file, "{$dir}/schema-{$hash}.php"));

                // stop event
                return false;
            }

            // delete invalid schema cache
        } catch (Error $e) {
            Event::emit('source.error', [$e]);
            File::rename($file, "{$dir}/{$name}.error.gql");
        }

        return null;
    }
}

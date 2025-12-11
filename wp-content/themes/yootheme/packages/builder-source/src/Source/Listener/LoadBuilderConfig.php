<?php

namespace YOOtheme\Builder\Source\Listener;

use YOOtheme\Builder\BuilderConfig;
use YOOtheme\Builder\Source;
use YOOtheme\Config;
use YOOtheme\GraphQL\SchemaPrinter;

class LoadBuilderConfig
{
    public Config $config;
    public Source $source;

    public function __construct(Config $config, Source $source)
    {
        $this->config = $config;
        $this->source = $source;
    }

    /**
     * @param BuilderConfig $config
     */
    public function handle($config): void
    {
        $dir = $this->config->get('image.cacheDir');
        $file = "{$dir}/schema-{$this->config->get('source.id')}.gql";
        $result = $this->source->queryIntrospection()->toArray();
        $content = SchemaPrinter::doPrint($this->source->getSchema());

        // update schema cache
        if (isset($result['data'])) {
            file_put_contents($file, $content);
        } elseif (is_file($file)) {
            unlink($file);
        }

        $config->merge(['schema' => $result['data']['__schema'] ?? $result]);
    }
}

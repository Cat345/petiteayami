<?php

namespace YOOtheme\Application;

use YOOtheme\Config;
use YOOtheme\ConfigObject;
use YOOtheme\Container;
use YOOtheme\Event;
use YOOtheme\Hook;

class ConfigLoader
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var array
     */
    protected $services = [];

    /**
     * Constructor.
     */
    public function __construct(Container $container)
    {
        $this->config = $container->get(Config::class);

        Hook::after('app.resolve', [$this, 'loadConfig']);
    }

    /**
     * Load configuration.
     */
    public function __invoke(Container $container, array $configs)
    {
        foreach ($configs as $config) {
            if ($config instanceof \Closure) {
                $config = $config($this->config, $container);
            } elseif (is_array($config)) {
                $config = $this->loadArray($config);
            }

            $this->config->add('', (array) $config);
        }
    }

    /**
     * After resolve service.
     *
     * @param mixed $service
     */
    public function loadConfig($service, string $id)
    {
        if (!$service instanceof ConfigObject) {
            return;
        }

        foreach ($this->services[$id] ?? [] as $value) {
            $service->merge(is_string($value) ? static::loadFile($value) : $value);
        }

        Event::emit($id, $service);
    }

    protected function loadArray(array $config)
    {
        foreach ($config as $key => $value) {
            if (str_contains($key, '\\') && str_ends_with($key, 'Config')) {
                $this->services[$key][] = $value;
                unset($config[$key]);
            }
        }

        return $config;
    }

    protected static function loadFile(string $file)
    {
        $type = pathinfo($file, PATHINFO_EXTENSION);

        if ($type === 'php') {
            return require $file;
        }

        if ($type === 'ini') {
            return parse_ini_file($file, true, INI_SCANNER_TYPED);
        }

        if ($type === 'json') {
            return json_decode(file_get_contents($file), true);
        }

        throw new \RuntimeException("Unable to load config file '{$file}'");
    }
}

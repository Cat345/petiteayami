<?php

namespace YOOtheme\Application;

use Closure;
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
     * @var array<string, list<string|array<string, mixed>>>
     */
    protected array $services = [];

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
     *
     * @param list<Closure|array<string, mixed>> $configs
     */
    public function __invoke(Container $container, array $configs): void
    {
        foreach ($configs as $config) {
            if ($config instanceof Closure) {
                $config = $config($this->config, $container);
            } else {
                $config = $this->loadArray((array) $config);
            }

            $this->config->add('', $config);
        }
    }

    /**
     * After resolve service.
     */
    public function loadConfig(?object $service, string $id): void
    {
        if (!$service instanceof ConfigObject) {
            return;
        }

        foreach ($this->services[$id] ?? [] as $value) {
            $service->merge(is_string($value) ? static::loadFile($value) : $value);
        }

        Event::emit($id, $service);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function loadArray(array $config): array
    {
        foreach ($config as $key => $value) {
            if (str_contains($key, '\\') && str_ends_with($key, 'Config')) {
                $this->services[$key][] = $value;
                unset($config[$key]);
            }
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    protected static function loadFile(string $file): array
    {
        $type = pathinfo($file, PATHINFO_EXTENSION);

        if ($type == 'php') {
            $result = require $file;
        } elseif ($type == 'ini') {
            $result = parse_ini_file($file, true, INI_SCANNER_TYPED);
        } elseif ($type == 'json') {
            $result = json_decode(file_get_contents($file) ?: '', true);
        }

        if (!is_array($result ?? null)) {
            throw new \RuntimeException("Unable to load config file '{$file}'");
        }

        return $result;
    }
}

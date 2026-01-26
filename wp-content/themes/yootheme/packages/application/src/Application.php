<?php

namespace YOOtheme;

use Exception;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use YOOtheme\Configuration\Configuration;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

/**
 * @property Response $response
 * @property Request $request
 */
class Application extends Container
{
    protected Config $config;

    /**
     * @var array<string,string|callable>
     */
    protected array $loaders = [];

    /**
     * @var ?static
     */
    protected static $instance;

    /**
     * Constructor.
     */
    public function __construct(?string $cache = null)
    {
        $this->config = new Configuration($cache);

        $this->set(static::class, $this);
        $this->setAlias(static::class, 'app');

        $this->set(Config::class, $this->config);
        $this->setAlias(Config::class, 'config');
    }

    /**
     * Gets global application.
     *
     * @return static
     */
    public static function getInstance(?string $cache = null): self
    {
        /** @phpstan-ignore new.static */
        return static::$instance ??= new static($cache);
    }

    /**
     * Run application.
     *
     * @return Response
     */
    public function run(bool $send = true, string $route = ''): ResponseInterface
    {
        $request = $this->get('request')->withAttribute('routePath', $route);

        try {
            $response = Event::emit('app.request|middleware', [$this, 'handle'], $request);
        } catch (Exception $exception) {
            $response = Event::emit('app.error|filter', $this->get('response'), $exception);
        }

        return $send ? $response->send() : $response;
    }

    /**
     * Handles a request.
     *
     * @throws Exception
     */
    public function handle(Request $request): Response
    {
        $this->set(Request::class, $request);

        /** @var Route $route */
        $route = $request->getAttribute('route');
        $result = $this->call($route->getCallable());

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result) || (is_object($result) && method_exists($result, '__toString'))) {
            return $this->response->write((string) $result);
        }

        return $this->response;
    }

    /**
     * Loads a bootstrap file.
     *
     * @return $this
     */
    public function load(string $files): self
    {
        $configs = [];

        foreach (File::glob($files, GLOB_NOSORT) as $file) {
            $configs = static::loadFile($file, $configs, ['app' => $this]);
        }

        if (isset($configs['loaders'])) {
            $this->loaders = array_merge($this->loaders, ...$configs['loaders']);
        }

        foreach (array_intersect_key($this->loaders, $configs) as $name => $loader) {
            if (is_string($loader) && class_exists($loader)) {
                $loader = $this->loaders[$name] = $this->resolveLoader($loader);
            }

            $loader($this, $configs[$name]);
        }

        return $this;
    }

    /**
     * Resolves a service instance.
     */
    protected function resolveService(string $id): ?object
    {
        return Hook::call([$id, 'app.resolve'], fn($id) => parent::resolveService($id), $id, $this);
    }

    /**
     * Resolves a loader instance.
     */
    protected function resolveLoader(string $loader): callable
    {
        return new $loader($this);
    }

    /**
     * Loads a bootstrap config.
     *
     * @param array<string,list<mixed>> $configs
     * @param array<string,mixed> $parameters
     *
     * @return array<string,list<mixed>>
     */
    protected static function loadFile(string $file, array $configs, array $parameters = []): array
    {
        extract($parameters, EXTR_SKIP);

        if (!is_array($config = require $file)) {
            throw new RuntimeException("Unable to load file '{$file}'");
        }

        foreach ($config as $key => $value) {
            $configs[$key][] = $value;
        }

        return $configs;
    }
}

<?php

namespace YOOtheme;

use Psr\Http\Message\ResponseInterface;
use YOOtheme\Configuration\Configuration;
use YOOtheme\Http\Request;
use YOOtheme\Http\Response;

/**
 * @property Response $response
 * @property Request $request
 */
class Application extends Container
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var array
     */
    protected $loaders = [];

    /**
     * @var static|null
     */
    protected static $instance;

    /**
     * Constructor.
     *
     * @param string $cache
     */
    public function __construct($cache = null)
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
     * @param null|mixed $cache
     *
     * @return static
     */
    public static function getInstance($cache = null)
    {
        return static::$instance ??= new static($cache);
    }

    /**
     * Run application.
     *
     * @param bool $send
     *
     * @return ResponseInterface
     */
    public function run($send = true)
    {
        try {
            $response = Event::emit('app.request|middleware', [$this, 'handle'], $this->request);
        } catch (\Exception $exception) {
            $response = Event::emit('app.error|filter', $this->response, $exception);
        }

        return $send ? $response->send() : $response;
    }

    /**
     * Handles a request.
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function handle(Request $request)
    {
        $this->set(Request::class, $request);

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
     * @param string $files
     *
     * @return $this
     */
    public function load($files)
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
     *
     * @param string $id
     *
     * @throws \Exception
     * @throws \ReflectionException
     *
     * @return mixed
     */
    protected function resolveService($id)
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
     */
    protected static function loadFile(string $file, array $configs, array $parameters = []): array
    {
        extract($parameters, EXTR_SKIP);

        if (!is_array($config = require $file)) {
            throw new \RuntimeException("Unable to load file '{$file}'");
        }

        foreach ($config as $key => $value) {
            $configs[$key][] = $value;
        }

        return $configs;
    }
}

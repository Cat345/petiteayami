<?php

namespace YOOtheme;

class EventDispatcher
{
    /**
     * @var array<string, callable>
     */
    protected array $handlers = [];

    /**
     * @var array<string, array<int, list<callable>>>
     */
    protected array $listeners = [];

    /**
     * Constructor.
     *
     * @param array<string, mixed> $handlers
     */
    public function __construct(array $handlers = [])
    {
        $this->handlers = $handlers + [
            'filter' => [$this, 'handleFilter'],
            'default' => [$this, 'handleDefault'],
            'middleware' => [$this, 'handleMiddleware'],
        ];
    }

    /**
     * Dispatches an event with arguments.
     *
     * @param string $event
     * @param mixed ...$arguments
     *
     * @return mixed
     */
    public function dispatch(string $event, ...$arguments)
    {
        [$event, $type] = explode('|', $event, 2) + [1 => 'default'];

        $handler = $this->handlers[$type] ?? $this->handlers['default'];

        return $handler($this->getListeners($event), $arguments);
    }

    /**
     * Gets the event listeners.
     *
     * @return list<callable>
     */
    public function getListeners(string $event): array
    {
        return array_merge(...$this->listeners[$event] ?? []);
    }

    /**
     * Adds an event listener.
     */
    public function addListener(string $event, callable $listener, int $priority = 0): void
    {
        $exists = isset($this->listeners[$event][$priority]);

        $this->listeners[$event][$priority][] = $listener;

        if (!$exists) {
            krsort($this->listeners[$event]);
        }
    }

    /**
     * Removes an event listener.
     */
    public function removeListener(string $event, ?callable $listener = null): bool
    {
        if (($result = is_null($listener)) || !isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        foreach ($this->listeners[$event] as &$listeners) {
            if (is_int($key = array_search($listener, $listeners, true))) {
                array_splice($listeners, $key, 1);
                return true;
            }
        }

        return $result;
    }

    /**
     * The default handler calls every listener ordered by the priority.
     *
     * @param list<callable> $listeners
     * @param list<mixed> $arguments
     *
     * @return mixed
     */
    protected function handleDefault(array $listeners, array $arguments = [])
    {
        $result = null;

        foreach ($listeners as $listener) {
            $_result = $listener(...$arguments);

            // stop event propagation?
            if ($_result === false) {
                break;
            }

            if (isset($_result)) {
                $result = $_result;
            }
        }

        return $result;
    }

    /**
     * The filter handler calls every listener ordered by the priority. It passes the return value from each listener to the next listener.
     *
     * @param list<callable> $listeners
     * @param list<mixed> $arguments
     *
     * @return mixed
     */
    protected function handleFilter(array $listeners, array $arguments = [])
    {
        if (!$arguments) {
            throw new \InvalidArgumentException('Filter must have at least one argument');
        }

        foreach ($listeners as $listener) {
            $arguments[0] = $listener(...$arguments);
        }

        return $arguments[0];
    }

    /**
     * The middleware handler calls every listener with arguments and a next() function which is invoked to execute the "next" middleware function.
     *
     * @param list<callable> $listeners
     * @param list<mixed> $arguments
     *
     * @return mixed
     */
    protected function handleMiddleware(array $listeners, array $arguments = [])
    {
        if (!$arguments) {
            throw new \InvalidArgumentException('Middleware must have at least one argument');
        }

        if (!is_callable($arguments[0])) {
            throw new \InvalidArgumentException(
                'Middleware must have a callable as first argument',
            );
        }

        $middleware = new Middleware(array_shift($arguments), $listeners);

        return $middleware(...$arguments);
    }
}

<?php

namespace Jenky\Hermes\Concerns;

use GuzzleHttp\HandlerStack;
use Illuminate\Support\Str;

trait InteractsWithConfiguration
{
    /**
     * Make the Guzzle client options from config.
     *
     * @param  array $config
     * @return array
     */
    public function makeClientOptions(array $config)
    {
        $options = $config['options'] ?? [];
        $options['handler'] = $options['handler'] ?? $this->createHandler($config);

        return $options;
    }

    /**
     * Create the client handler stack instance.
     *
     * @param  array $config
     * @return \GuzzleHttp\HandlerStack
     */
    public function createHandler(array $config = [])
    {
        return $this->prepareHandler(
            HandlerStack::create($this->handler($config)), $config
        );
    }

    /**
     * Get the handle stack's handler instance.
     *
     * @param  array $config
     * @return mixed
     */
    public function handler(array $config)
    {
        if (empty($config['handler'])) {
            return;
        }

        $factory = is_callable($handler = $config['handler'])
            ? $handler
            : $this->app->make($handler, $config['with'] ?? []);

        return $factory($config);
    }

    /**
     * Prepare handler stack for usage by Guzzle client.
     *
     * @param  \GuzzleHttp\HandlerStack $handler
     * @param  array $config
     * @return \GuzzleHttp\HandlerStack
     */
    protected function prepareHandler(HandlerStack $handler, array $config = [])
    {
        foreach ($this->middleware($config) as [$middleware, $name]) {
            $handler->push($middleware, $name);
        }

        return $this->tap($handler, $config);
    }

    /**
     * Apply the configured taps for the handle stack.
     *
     * @param  \GuzzleHttp\HandlerStack  $handler
     * @param  array  $config
     * @return \GuzzleHttp\HandlerStack
     */
    protected function tap(HandlerStack $handler, array $config = [])
    {
        foreach ($config['tap'] ?? [] as $tap) {
            [$class, $arguments] = $this->parseTap($tap);

            $this->app->make($class)->__invoke(
                $handler, ...array_filter(explode(',', $arguments))
            );
        }

        return $handler;
    }

    /**
     * Parse the given tap class string into a class name and arguments string.
     *
     * @param  string  $tap
     * @return array
     */
    protected function parseTap($tap)
    {
        return Str::contains($tap, ':') ? explode(':', $tap, 2) : [$tap, ''];
    }

    /**
     * Parse the given class string into a class name and arguments array.
     *
     * @param  mixed $key
     * @param  mixed $value
     * @return array
     */
    protected function parseClassAndArguments($key, $value)
    {
        if (is_string($key) && is_array($value)) {
            return [$key, $value];
        }

        return [$value, []];
    }

    /**
     * Get all middleware that will be pushed to handle stack instance.
     *
     * @param  array $config
     * @return array
     */
    public function middleware(array $config)
    {
        $middleware = [];

        foreach (array_merge($config['middleware'] ?? [], $config['interceptors'] ?? []) as $key => $value) {
            $middleware[] = $this->parseMiddleware($key, $value);
        }

        return $middleware;
    }

    /**
     * Parse the given middleware and create middleware instance with it's parameters.
     *
     * @param  mixed $key
     * @param  mixed $value
     * @return array
     */
    protected function parseMiddleware($key, $value)
    {
        $name = is_numeric($key) ? '' : $key;

        if (is_callable($value)) {
            return [$value, $name];
        }

        [$class, $arguments] = $this->parseClassAndArguments($key, $value);

        return [$this->app->make($class, $arguments), $class];
    }
}

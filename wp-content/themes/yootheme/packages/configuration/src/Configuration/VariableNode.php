<?php

namespace YOOtheme\Configuration;

class VariableNode extends Node
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function resolve(array $params)
    {
        $arguments = $this->resolveArgs([$this->name], $params);

        return $params['config']->get(...$arguments);
    }

    /**
     * @inheritdoc
     */
    public function compile(array $params): string
    {
        $arguments = $this->compileArgs([$this->name], $params);

        return "\$config->get({$arguments})";
    }
}

<?php

namespace YOOtheme\Configuration;

class StringNode extends Node
{
    protected string $format;

    /**
     * @var list<FilterNode|VariableNode>
     */
    protected array $arguments;

    /**
     * Constructor.
     *
     * @param list<FilterNode|VariableNode> $arguments
     */
    public function __construct(string $format, array $arguments = [])
    {
        $this->format = $format;
        $this->arguments = $arguments;
    }

    /**
     * @inheritdoc
     */
    public function resolve(array $params)
    {
        $arguments = array_merge([$this->format], $this->arguments);
        $arguments = $this->resolveArgs($arguments, $params);

        return sprintf(...$arguments);
    }

    /**
     * @inheritdoc
     */
    public function compile(array $params): string
    {
        $arguments = array_merge([$this->format], $this->arguments);
        $arguments = $this->compileArgs($arguments, $params);

        return "sprintf({$arguments})";
    }
}

<?php

namespace YOOtheme\Builder\Source\Query;

class Node implements \JsonSerializable
{
    public string $kind;
    public ?string $name;
    public ?string $alias = null;

    /**
     * @var list<Node>
     */
    public array $children = [];

    /**
     * @var array<string, mixed>
     */
    public array $arguments = [];

    /**
     * @var list<Node>
     */
    public array $directives = [];

    /**
     * Constructor.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(string $kind, ?string $name, array $options = [])
    {
        $this->kind = $kind;
        $this->name = $name;

        foreach ($options as $key => $value) {
            $this->$key = $value;
        }
    }

    public function get(string $name): ?object
    {
        foreach ($this->children as $child) {
            if ($child->name === $name) {
                return $child;
            }
        }

        return null;
    }

    public function query(?string $name = null): self
    {
        static::assertNode($this, 'Document');

        return $this->children[] = new self('Query', $name);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function field(string $name, array $arguments = []): self
    {
        static::assertNode($this, 'Field', 'Query');

        return $this->children[] = new self('Field', $name, ['arguments' => $arguments]);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function directive(string $name, array $arguments = []): self
    {
        static::assertNode($this, 'Field');

        return $this->directives[] = new self('Directive', $name, ['arguments' => $arguments]);
    }

    /**
     * @return array<object>
     */
    public function toAST()
    {
        return AST::build($this);
    }

    public function toHash(): string
    {
        return hash('crc32b', json_encode($this));
    }

    /**
     * @return list<mixed>
     */
    public function jsonSerialize(): array
    {
        return array_values(
            array_filter([$this->kind, $this->name, $this->arguments, $this->directives]),
        );
    }

    public static function document(): self
    {
        return new self('Document', null);
    }

    protected static function assertNode(self $node, string ...$kind): void
    {
        if (!in_array($node->kind, $kind, true)) {
            throw new \Exception('Node must be a ' . join(', ', $kind));
        }
    }
}

<?php

namespace YOOtheme\Http;

use Psr\Http\Message\StreamInterface;

class CallbackStream implements StreamInterface
{
    /**
     * @var bool
     */
    protected $called = false;

    /**
     * @var callable
     */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return $this->getContents();
    }

    /**
     * @inheritdoc
     */
    public function getContents(): string
    {
        if ($this->called) {
            return '';
        }

        $this->called = true;

        return ($this->callback)();
    }

    /**
     * @inheritdoc
     */
    public function close(): void {}

    /**
     * @inheritdoc
     */
    public function detach()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getSize(): ?int
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function tell(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function eof(): bool
    {
        return $this->called;
    }

    /**
     * @inheritdoc
     */
    public function isSeekable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function seek($offset, $whence = SEEK_SET): void {}

    /**
     * @inheritdoc
     */
    public function rewind(): void {}

    /**
     * @inheritdoc
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function write($string): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function read($length): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($key = null)
    {
        return $key === null ? [] : null;
    }
}

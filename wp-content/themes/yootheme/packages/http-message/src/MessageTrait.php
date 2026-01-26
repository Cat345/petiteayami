<?php

namespace YOOtheme\Http;

trait MessageTrait
{
    /**
     * Gets content type.
     */
    public function getContentType(): ?string
    {
        return $this->hasHeader('content-type')
            ? (string) $this->getHeader('content-type')[0]
            : null;
    }

    /**
     * Gets content length.
     */
    public function getContentLength(): ?int
    {
        return $this->hasHeader('content-length')
            ? (int) $this->getHeader('content-length')[0]
            : null;
    }
}

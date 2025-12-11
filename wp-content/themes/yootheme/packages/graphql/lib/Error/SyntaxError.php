<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Error;

use YOOtheme\GraphQL\Language\Source;

class SyntaxError extends Error
{
    public function __construct(Source $source, int $position, string $description)
    {
        parent::__construct(
            "Syntax Error: {$description}",
            null,
            $source,
            [$position]
        );
    }
}

<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Type\Definition;

#[\Attribute(\Attribute::TARGET_ALL)]
class Description
{
    public function __construct(
        public string $description,
    ) {}
}

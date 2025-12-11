<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\DocumentNode;
use YOOtheme\GraphQL\Type\Schema;

interface ValidationContext
{
    public function reportError(Error $error): void;

    /** @return list<Error> */
    public function getErrors(): array;

    public function getDocument(): DocumentNode;

    public function getSchema(): ?Schema;
}

<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Server;

use YOOtheme\GraphQL\Error\ClientAware;

class RequestError extends \Exception implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }
}

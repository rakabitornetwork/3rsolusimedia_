<?php

namespace App\Exceptions;

use App\Models\MikrotikRouter;
use RuntimeException;

class InactiveMikrotikRouterException extends RuntimeException
{
    public static function for(MikrotikRouter $router): self
    {
        return new self($router->connectionBlockedMessage());
    }
}

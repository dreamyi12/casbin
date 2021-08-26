<?php

namespace Dreamyi12\Casbin\Exceptions;

use App\Constants\ErrorCode;
use Hyperf\Server\Exception\ServerException;
use Throwable;

class UnauthorizedException extends ServerException
{
    /**
     * Create a new exception instance.
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $message = empty($message) ? ErrorCode::getMessage(ErrorCode::UNAUTHORIZED) : $message;
        $code = empty($code) ? ErrorCode::UNAUTHORIZED : $code;
        parent::__construct($message, $code, $previous);
    }
}

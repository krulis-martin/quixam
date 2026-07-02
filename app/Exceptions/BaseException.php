<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Nette\Http\IResponse;

/**
 * The great grandfather of almost all exceptions which can occur
 * in whole application. In addition to classical exceptions, this one adds
 * a bit of spices to the mix with custom defined error code and error
 * parameters.
 */
class BaseException extends Exception
{
    protected int $httpCode = IResponse::S500_InternalServerError;

    /**
     * @param string $msg Error message
     * @param int $code Error code
     * @param Exception $previous Previous exception
     */
    public function __construct(
        string $msg = "Unexpected internal error",
        int $code = IResponse::S500_InternalServerError,
        ?Exception $previous = null
    ) {
        parent::__construct($msg, $code, $previous);
        $this->httpCode = $code;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}

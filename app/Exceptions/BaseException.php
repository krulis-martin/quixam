<?php

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

    /**
     * Constructor.
     * @param string $msg Error message
     * @param int $code Error code
     * @param Exception $previous Previous exception
     */
    public function __construct(
        $msg = "Unexpected internal error",
        $code = IResponse::S500_INTERNAL_SERVER_ERROR,
        $previous = null
    ) {
        parent::__construct($msg, $code, $previous);
    }
}

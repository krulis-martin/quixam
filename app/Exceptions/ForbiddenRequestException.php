<?php

declare(strict_types=1);

namespace App\Exceptions;

use Nette\Http\IResponse;
use Exception;

/**
 * Thrown when the request is forbidden, e.g. when user tries to access a resource they do not have permissions for.
 */
class ForbiddenRequestException extends BaseException
{
    /**
     * Creates instance with further description.
     * @param string $msg description
     * @param int $code HTTP status code, default is 403 Forbidden
     * @param Exception $previous Previous exception
     */
    public function __construct(
        string $msg = "Forbidden Request - Access denied",
        $code = IResponse::S403_Forbidden,
        $previous = null
    ) {
        parent::__construct($msg, $code, $previous);
    }
}

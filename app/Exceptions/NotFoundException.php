<?php

namespace App\Exceptions;

use Nette\Http\IResponse;
use Exception;

/**
 * Used when requested resource was not found.
 */
class NotFoundException extends BaseException
{
    /**
     * Creates instance with further description.
     * @param string $msg description
     * @param Exception $previous Previous exception
     */
    public function __construct(
        string $msg = 'The resource you requested was not found.',
        $previous = null
    ) {
        parent::__construct(
            "Not Found - $msg",
            IResponse::S404_NOT_FOUND,
            $previous
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Helpers;

use Nette\Http\IResponse;
use App\Exceptions\BaseException;
use Exception;

/**
 * Used whenever something goes wrong with questions (loading, processing, ...).
 */
class QuestionException extends BaseException
{
    /**
     * Creates instance with further description.
     * @param string $msg description
     * @param Exception $previous Previous exception
     */
    public function __construct(
        string $msg = 'Internal error in question processing.',
        $previous = null
    ) {
        parent::__construct(
            "Not Found - $msg",
            IResponse::S500_INTERNAL_SERVER_ERROR,
            $previous
        );
    }
}

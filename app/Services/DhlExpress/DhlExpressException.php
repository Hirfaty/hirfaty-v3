<?php

namespace App\Services\DhlExpress;

use RuntimeException;

/**
 * DHL Express answered with an error, or could not be reached at all.
 * `status` is the HTTP status, null when no answer came back; `details` are
 * the field-by-field reasons DHL lists under a validation error.
 */
class DhlExpressException extends RuntimeException
{
    /**
     * @param  list<string>  $details
     */
    public function __construct(string $message, public readonly ?int $status = null, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}

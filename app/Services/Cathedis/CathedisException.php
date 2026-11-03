<?php

namespace App\Services\Cathedis;

use RuntimeException;

/**
 * Cathedis answered with an error, or could not be reached at all. `status`
 * is the HTTP status, null when no answer came back.
 */
class CathedisException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}

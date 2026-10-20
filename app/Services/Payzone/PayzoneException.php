<?php

namespace App\Services\Payzone;

use RuntimeException;

/**
 * Payzone's API answered with an error, or could not be reached at all.
 *
 * `errorCode` is Payzone's machine-readable reason (`authentication_error`,
 * `entity_not_found_error`…) when it gave one; null for a timeout or a 5xx.
 */
class PayzoneException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        public readonly array $body = [],
    ) {
        parent::__construct($message);
    }
}

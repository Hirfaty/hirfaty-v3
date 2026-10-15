<?php

namespace App\Services\PayPal;

use RuntimeException;

/**
 * PayPal answered with an error, or could not be reached at all.
 *
 * `issue` is PayPal's machine-readable reason (`INSTRUMENT_DECLINED`,
 * `ORDER_ALREADY_CAPTURED`…) when it gave one; null for a timeout or a 5xx,
 * where nobody knows whether anything happened.
 */
class PayPalException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $issue = null,
        public readonly array $body = [],
    ) {
        parent::__construct($message);
    }
}

<?php

namespace App\Domain\Payments\Exceptions;

use RuntimeException;

/**
 * Thrown when somebody tries to band a disbursement.
 *
 * The four refund bands are written entirely about Summit's professional fee —
 * band C, for instance, refunds "the portion allocated to stages not yet
 * performed". Applied to an authority's charge that has already been committed,
 * that sentence produces a refund Summit would have to fund out of its own
 * pocket. So the calculator refuses, and asks for a figure a person can justify.
 */
class DisbursementNeedsDocumentedRefund extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'An authority or third-party charge cannot be refunded by band. '
            .'Enter the amount actually recoverable and the reason it is recoverable.'
        );
    }
}

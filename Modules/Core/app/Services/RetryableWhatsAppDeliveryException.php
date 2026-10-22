<?php

namespace Modules\Core\Services;

use RuntimeException;

/** A provider response explicitly indicates that retrying the send is appropriate. */
class RetryableWhatsAppDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        public readonly string $errorCode = 'meta_transient_error',
    ) {
        parent::__construct($message);
    }
}

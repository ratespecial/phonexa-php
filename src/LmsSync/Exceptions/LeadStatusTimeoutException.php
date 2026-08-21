<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Exceptions;

use RuntimeException;

/**
 * waitForLeadStatus() gave up while the lead was still in progress.
 *
 * Unlike the other Phonexa exceptions this does not extend PhonexaException: there is no
 * failed HTTP response to carry, since every poll succeeded — the auction simply had not
 * finished. The lead may still sell, so the checkKey is preserved for a later retry.
 */
class LeadStatusTimeoutException extends RuntimeException
{
    public function __construct(
        private readonly string $checkKey,
        private readonly int $timeout,
    ) {
        parent::__construct(sprintf(
            'Lead "%s" was still in progress after %d seconds.',
            $checkKey,
            $timeout,
        ));
    }

    public function getCheckKey(): string
    {
        return $this->checkKey;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}

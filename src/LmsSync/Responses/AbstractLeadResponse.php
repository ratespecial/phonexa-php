<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Responses;

/**
 * Fields and status vocabulary shared by both LMS Sync endpoints.
 *
 * Phonexa answers with HTTP 200 regardless of outcome and reports the result in the `status`
 * field, so this is where a caller learns what actually happened. Statuses 4 and 5 never
 * reach a DTO — the requests throw for those.
 */
abstract class AbstractLeadResponse
{
    /**
     * @var int The buyer accepted the lead. `redirect_url` and `price` are populated.
     */
    public const int STATUS_SOLD = 1;

    /**
     * @var int No buyer took the lead. A normal business outcome, not an error.
     */
    public const int STATUS_REJECT = 2;

    /**
     * @var int Posted with closeConnection=1 and still being auctioned. Poll for the result.
     */
    public const int STATUS_IN_PROGRESS = 3;

    /**
     * @var int Validation or authorisation failure. Raises LeadValidationException.
     */
    public const int STATUS_ERROR = 4;

    /**
     * @var int Unknown checkKey. Raises LeadNotFoundException.
     */
    public const int STATUS_NOT_FOUND = 5;

    public function __construct(
        public readonly int $status,
        public readonly string $statusText,
        public readonly ?string $redirectUrl = null,
    ) {}

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECT;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }
}

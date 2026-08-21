<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Exceptions;

use Ratespecial\Phonexa\Exceptions\PhonexaException;
use Saloon\Http\Response;
use Throwable;

/**
 * Phonexa answered with status 5 — the checkKey does not match a known lead.
 */
class LeadNotFoundException extends PhonexaException
{
    public function __construct(
        Response $response,
        private readonly string $checkKey = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            response: $response,
            message: $checkKey === ''
                ? 'Phonexa could not find the requested lead.'
                : sprintf('Phonexa could not find lead "%s".', $checkKey),
            previous: $previous,
        );
    }

    public function getCheckKey(): string
    {
        return $this->checkKey;
    }
}

<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Exceptions;

use Ratespecial\Phonexa\Exceptions\PhonexaException;
use Saloon\Http\Response;
use Throwable;

/**
 * Phonexa answered with status 4 — the request failed validation or authorisation.
 *
 * Note this arrives over HTTP 200, so it is not caught by Saloon's normal failure handling;
 * the request classes raise it while building their DTO.
 */
class LeadValidationException extends PhonexaException
{
    /**
     * @var array<int, mixed> Phonexa's raw `errors` list, each entry keyed by field name.
     */
    private readonly array $errors;

    /**
     * @param  array<string, mixed>  $body  The decoded response body.
     */
    public function __construct(Response $response, array $body, ?Throwable $previous = null)
    {
        $this->errors = self::extractErrors($body);

        parent::__construct(
            response: $response,
            message: $this->buildMessage(self::flattenErrors($this->errors)),
            previous: $previous,
        );
    }

    /**
     * Build the exception for a status 4 body, narrowing to a subclass where the errors warrant it.
     *
     * Phonexa reports duplicates as an ordinary validation error, so the distinction can only
     * be made from the error text.
     *
     * @param  array<string, mixed>  $body  The decoded response body.
     */
    public static function fromBody(Response $response, array $body, ?Throwable $previous = null): self
    {
        if (DuplicateLeadException::matches(self::extractErrors($body))) {
            return new DuplicateLeadException($response, $body, $previous);
        }

        return new self($response, $body, $previous);
    }

    /**
     * @return array<int, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Subclasses override this to describe their own flavour of rejection.
     */
    protected function buildMessage(string $flattenedErrors): string
    {
        return $flattenedErrors === ''
            ? 'Phonexa rejected the request but reported no errors.'
            : $flattenedErrors;
    }
}

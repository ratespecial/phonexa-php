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
     * Phonexa reports duplicates and account problems as ordinary validation errors, so the
     * distinction can only be made from the error text.
     *
     * @param  array<string, mixed>  $body  The decoded response body.
     */
    public static function fromBody(Response $response, array $body, ?Throwable $previous = null): self
    {
        $errors = self::extractErrors($body);

        return match (true) {
            DuplicateLeadException::matches($errors)       => new DuplicateLeadException($response, $body, $previous),
            UserDisabledException::matches($errors)        => new UserDisabledException($response, $body, $previous),
            AuthorizationFailedException::matches($errors) => new AuthorizationFailedException($response, $body, $previous),
            default                                        => new self($response, $body, $previous),
        };
    }

    /**
     * @return array<int, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Does any error text in an `errors` list satisfy the test?
     *
     * Phonexa is inconsistent about where it puts the text: sometimes it keys the entry by the
     * message and repeats it as the value (`{"Duplicate Application":"Duplicate Application"}`),
     * sometimes it keys it with an empty value (`{"Authorization Failed":""}`), and sometimes it
     * sends a bare list (`["Current user is disabled. ..."]`). Every key and scalar value is
     * offered to the test, so subclasses only decide what text means.
     *
     * @param  array<int, mixed>  $errors
     * @param  callable(string): bool  $matchesText
     */
    protected static function errorsContain(array $errors, callable $matchesText): bool
    {
        foreach ($errors as $error) {
            if (is_string($error) && $matchesText($error)) {
                return true;
            }

            if (! is_array($error)) {
                continue;
            }

            foreach ($error as $key => $message) {
                if (is_string($key) && $matchesText($key)) {
                    return true;
                }

                if (is_scalar($message) && $matchesText((string) $message)) {
                    return true;
                }
            }
        }

        return false;
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

<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Exceptions;

/**
 * Phonexa answered with status 4 because it did not accept the API credentials.
 *
 * Arrives as an ordinary validation failure keyed by the message with an empty value, in either
 * a short form (per the API spec) or a longer one seen live:
 *
 *  - `{"status":4,"errors":[{"Authorization Failed":""}]}`
 *  - `{"status":4,"errors":[{"Authorization Failed. ApiId and ApiPassword you entered don't match.":""}]}`
 *
 * for a wrong apiId/apiPassword pair, on both lead posts and status checks.
 */
class AuthorizationFailedException extends LeadValidationException
{
    /**
     * @var string "Authorization Failed" as the whole message, or as its opening sentence. The
     *             phrase merely starting other wording ("Authorization Failed earlier, retried")
     *             is not this error.
     */
    private const string AUTHORIZATION_FAILED_PATTERN = '/^authorization failed(?:[.:!]|$)/i';

    /**
     * Does this `errors` list report rejected credentials?
     *
     * @param  array<int, mixed>  $errors
     */
    public static function matches(array $errors): bool
    {
        return self::errorsContain(
            $errors,
            static fn (string $text): bool => preg_match(self::AUTHORIZATION_FAILED_PATTERN, trim($text)) === 1,
        );
    }

    protected function buildMessage(string $flattenedErrors): string
    {
        return $flattenedErrors === ''
            ? 'Phonexa rejected the API credentials.'
            : $flattenedErrors;
    }
}

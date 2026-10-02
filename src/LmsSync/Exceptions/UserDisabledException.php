<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Exceptions;

/**
 * Phonexa answered with status 4 because the API user the credentials belong to is disabled.
 *
 * Arrives as an ordinary validation failure carrying a bare message —
 * `{"status":4,"errors":[["Current user is disabled. Please contact your account manager."]]}` —
 * before any field is checked. Every lead posted with these credentials fails the same way until
 * the user is re-enabled in Phonexa, so it is an account problem rather than a bad lead.
 */
class UserDisabledException extends LeadValidationException
{
    /**
     * @var string Text Phonexa uses for a disabled user, matched case-insensitively within the message.
     */
    private const string USER_DISABLED_TEXT = 'user is disabled';

    /**
     * Does this `errors` list report a disabled user?
     *
     * @param  array<int, mixed>  $errors
     */
    public static function matches(array $errors): bool
    {
        return self::errorsContain(
            $errors,
            static fn (string $text): bool => str_contains(strtolower($text), self::USER_DISABLED_TEXT),
        );
    }

    protected function buildMessage(string $flattenedErrors): string
    {
        return $flattenedErrors === ''
            ? 'Phonexa reports the API user is disabled.'
            : $flattenedErrors;
    }
}

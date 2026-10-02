<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Exceptions;

/**
 * Phonexa answered with status 4 because it did not accept the API credentials.
 *
 * Arrives as an ordinary validation failure keyed by the message with an empty value —
 * `{"status":4,"errors":[{"Authorization Failed":""}]}` — for a wrong apiId/apiPassword pair,
 * on both lead posts and status checks.
 */
class AuthorizationFailedException extends LeadValidationException
{
    /**
     * @var string Text Phonexa uses for rejected credentials, matched case-insensitively.
     */
    private const string AUTHORIZATION_FAILED_TEXT = 'authorization failed';

    /**
     * Does this `errors` list report rejected credentials?
     *
     * @param  array<int, mixed>  $errors
     */
    public static function matches(array $errors): bool
    {
        return self::errorsContain(
            $errors,
            static fn (string $text): bool => strtolower(trim($text)) === self::AUTHORIZATION_FAILED_TEXT,
        );
    }

    protected function buildMessage(string $flattenedErrors): string
    {
        return $flattenedErrors === ''
            ? 'Phonexa rejected the API credentials.'
            : $flattenedErrors;
    }
}

<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\LmsSync\Exceptions;

/**
 * Phonexa answered with status 4 because the lead has already been submitted.
 *
 * A duplicate arrives as an ordinary validation failure — `{"status":4,"errors":[{"Duplicate
 * Application":"Duplicate Application"}]}` — so this is a narrowing of LeadValidationException
 * rather than a separate status. Catch it before its parent to treat a re-post differently
 * from a genuinely malformed lead.
 */
class DuplicateLeadException extends LeadValidationException
{
    /**
     * @var array<int, string> Error texts Phonexa uses for a duplicate, matched case-insensitively.
     */
    private const array DUPLICATE_ERRORS = [
        'duplicate application',
    ];

    /**
     * Does this `errors` list describe a duplicate rather than a validation problem?
     *
     * Phonexa keys the entry by the error text and repeats it as the value, so either side
     * is checked.
     *
     * @param  array<int, mixed>  $errors
     */
    public static function matches(array $errors): bool
    {
        foreach ($errors as $error) {
            if (is_string($error) && self::isDuplicateText($error)) {
                return true;
            }

            if (! is_array($error)) {
                continue;
            }

            foreach ($error as $field => $message) {
                if (self::isDuplicateText((string) $field)) {
                    return true;
                }

                if (is_scalar($message) && self::isDuplicateText((string) $message)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function buildMessage(string $flattenedErrors): string
    {
        return $flattenedErrors === ''
            ? 'Phonexa rejected the lead as a duplicate.'
            : $flattenedErrors;
    }

    private static function isDuplicateText(string $text): bool
    {
        return in_array(strtolower(trim($text)), self::DUPLICATE_ERRORS, true);
    }
}

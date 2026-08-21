<?php

declare(strict_types=1);

namespace Ratespecial\Phonexa\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * Base exception for every Phonexa sub-service.
 *
 * Extends Saloon's RequestException so the failing Response is always reachable via
 * getResponse(), and so callers may catch either this class or Saloon's.
 */
class PhonexaException extends RequestException
{
    /**
     * Flatten Phonexa's `errors` array into a readable string.
     *
     * Phonexa returns errors as a list of single-entry objects keyed by field name,
     * e.g. `[{"email": "required"}, {"Not Found": ""}]`.
     *
     * @param  array<int, mixed>  $errors
     */
    protected static function flattenErrors(array $errors): string
    {
        $parts = [];

        foreach ($errors as $error) {
            if (is_string($error)) {
                $parts[] = $error;

                continue;
            }

            if (! is_array($error)) {
                continue;
            }

            foreach ($error as $field => $message) {
                $parts[] = is_scalar($message) && (string) $message !== ''
                    ? $field . ': ' . $message
                    : (string) $field;
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Pull the `errors` array out of a decoded Phonexa response body.
     *
     * @param  array<string, mixed>  $body
     * @return array<int, mixed>
     */
    protected static function extractErrors(array $body): array
    {
        $errors = $body['errors'] ?? [];

        return is_array($errors) ? array_values($errors) : [];
    }
}

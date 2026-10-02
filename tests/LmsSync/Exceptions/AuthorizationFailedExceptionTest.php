<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa\LmsSync\Exceptions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ratespecial\Phonexa\LmsSync\Exceptions\AuthorizationFailedException;

class AuthorizationFailedExceptionTest extends TestCase
{
    /**
     * @param  array<int, mixed>  $errors
     */
    #[DataProvider('errorsProvider')]
    public function testMatches(array $errors, bool $expected): void
    {
        $this->assertSame($expected, AuthorizationFailedException::matches($errors));
    }

    /**
     * @return array<string, array{array<int, mixed>, bool}>
     */
    public static function errorsProvider(): array
    {
        return [
            'phonexa keyed, empty value' => [[['Authorization Failed' => '']], true],
            'as a value'                 => [[['error' => 'Authorization Failed']], true],
            'bare string'                => [['Authorization Failed'], true],
            'differing case'             => [[['authorization failed' => '']], true],
            'padded'                     => [[[' Authorization Failed ' => '']], true],
            'ordinary validation'        => [[['email' => 'required']], false],
            'unrelated wording'          => [[['notes' => 'Authorization Failed earlier, retried']], false],
            'extended message'           => [[["Authorization Failed. ApiId and ApiPassword you entered don't match." => '']], true],
            'empty'                      => [[], false],
        ];
    }
}

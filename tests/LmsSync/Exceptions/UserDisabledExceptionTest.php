<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa\LmsSync\Exceptions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ratespecial\Phonexa\LmsSync\Exceptions\UserDisabledException;

class UserDisabledExceptionTest extends TestCase
{
    /**
     * @param  array<int, mixed>  $errors
     */
    #[DataProvider('errorsProvider')]
    public function testMatches(array $errors, bool $expected): void
    {
        $this->assertSame($expected, UserDisabledException::matches($errors));
    }

    /**
     * @return array<string, array{array<int, mixed>, bool}>
     */
    public static function errorsProvider(): array
    {
        $phonexa = 'Current user is disabled. Please contact your account manager.';

        return [
            'phonexa bare list'   => [[[$phonexa]], true],
            'bare string'         => [[$phonexa], true],
            'keyed by the text'   => [[[$phonexa => '']], true],
            'as a value'          => [[['error' => $phonexa]], true],
            'differing case'      => [[['CURRENT USER IS DISABLED.']], true],
            'among other errors'  => [[['email' => 'required'], [$phonexa]], true],
            'ordinary validation' => [[['email' => 'required'], ['zip' => 'required']], false],
            'unrelated wording'   => [[['user' => 'disabled checkbox required']], false],
            'empty'               => [[], false],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Ratespecial\Phonexa\LmsSync\Exceptions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ratespecial\Phonexa\LmsSync\Exceptions\DuplicateLeadException;

class DuplicateLeadExceptionTest extends TestCase
{
    /**
     * @param  array<int, mixed>  $errors
     */
    #[DataProvider('errorsProvider')]
    public function testMatches(array $errors, bool $expected): void
    {
        $this->assertSame($expected, DuplicateLeadException::matches($errors));
    }

    /**
     * @return array<string, array{array<int, mixed>, bool}>
     */
    public static function errorsProvider(): array
    {
        return [
            'phonexa duplicate'   => [[['Duplicate Application' => 'Duplicate Application']], true],
            'value only'          => [[['error' => 'Duplicate Application']], true],
            'bare string'         => [['Duplicate Application'], true],
            'differing case'      => [[['duplicate application' => '']], true],
            'padded'              => [[[' Duplicate Application ' => '']], true],
            'among other errors'  => [[['email' => 'required'], ['Duplicate Application' => '']], true],
            'ordinary validation' => [[['email' => 'required'], ['zip' => 'required']], false],
            'unrelated wording'   => [[['leadId' => 'duplicate of an existing record']], false],
            'empty'               => [[], false],
        ];
    }
}

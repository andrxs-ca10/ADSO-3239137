<?php

namespace Tests\Unit;

use App\Rules\ValidEan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidEanTest extends TestCase
{
    #[DataProvider('eanCases')]
    public function test_it_validates_ean(mixed $value, bool $expected): void
    {
        $valid = true;

        (new ValidEan())->validate('ean', $value, function (string $message) use (&$valid): void {
            $valid = false;
        });

        self::assertSame($expected, $valid);
    }

    public static function eanCases(): array
    {
        return [
            'EAN-8 válido' => ['96385074', true],
            'EAN-13 válido' => ['4006381333931', true],
            'cero inicial' => ['0123456789012', true],
            'dígito incorrecto' => ['4006381333932', false],
            'longitud incorrecta' => ['1234567', false],
            'contiene letras' => ['9638507A', false],
            'enviado como número' => [96385074, false],
        ];
    }
}
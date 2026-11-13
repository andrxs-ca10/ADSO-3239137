<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEan implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/\A(?:[0-9]{8}|[0-9]{13})\z/', $value) !== 1) {
            $fail('El EAN debe enviarse como texto y contener 8 o 13 dígitos.');

            return;
        }

        $lastIndex = strlen($value) - 1;
        $sum = 0;
        $weight = 3;

        for ($index = $lastIndex - 1; $index >= 0; $index--) {
            $sum += (int) $value[$index] * $weight;
            $weight = 4 - $weight;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        if ((int) $value[$lastIndex] !== $checkDigit) {
            $fail('El dígito de control del EAN es incorrecto.');
        }
    }
}
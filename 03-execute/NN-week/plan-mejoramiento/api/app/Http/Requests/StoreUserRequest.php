<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $username = $this->input('username');

        if (is_string($username)) {
            $this->merge(['username' => Str::lower(trim($username))]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => [
                'bail',
                'required',
                'string',
                'min:8',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (strlen($value) > 72 || str_contains($value, "\0")) {
                        $fail('La contraseña admite hasta 72 bytes y no puede contener caracteres nulos.');
                    }
                },
            ],
            'role' => ['required', 'string', 'in:operator'],
        ];
    }
}
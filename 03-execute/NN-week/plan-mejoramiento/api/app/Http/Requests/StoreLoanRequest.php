<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreLoanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'member_id' => ['bail', 'required', 'uuid'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:4'],
            'items.*' => ['required', 'array:game_id'],
            'items.*.game_id' => ['bail', 'required', 'uuid', 'distinct:ignore_case'],
        ];
    }
        protected function failedValidation(Validator $validator): void
    {
        foreach ($validator->failed() as $field => $rules) {
            if (! isset($rules['Distinct'])) {
                continue;
            }

            $message = 'No puedes incluir el mismo juego más de una vez en el préstamo.';

            throw new HttpResponseException(response()->json([
                'code' => 'DUPLICATE_GAME_IN_LOAN',
                'message' => $message,
                'details' => [['field' => $field, 'issue' => $message]],
            ], 422));
        }

        parent::failedValidation($validator);
    }
}
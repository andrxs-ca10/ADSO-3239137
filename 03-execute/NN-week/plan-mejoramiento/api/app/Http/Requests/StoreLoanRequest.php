<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'designer' => ['required', 'string', 'max:255'],
            'category_id' => ['bail', 'required', 'uuid', 'exists:categories,id'],
            'total_units' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
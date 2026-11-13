<?php

namespace App\Http\Requests;

use App\Rules\ValidEan;
use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'designer' => ['required', 'string', 'max:255'],
            'ean' => ['bail', 'required', new ValidEan()],
            'category_id' => ['bail', 'required', 'uuid', 'exists:categories,id'],
            'total_units' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
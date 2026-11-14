<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'max:2048', 'image', 'mimetypes:image/jpeg,image/png'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Debes enviar una portada.',
            'file.file' => 'La portada debe ser un archivo.',
            'file.max' => 'La portada no puede superar los 2 MB.',
            'file.image' => 'La portada debe ser una imagen válida.',
            'file.mimetypes' => 'La portada debe estar en formato JPEG o PNG.',
        ];
    }
}
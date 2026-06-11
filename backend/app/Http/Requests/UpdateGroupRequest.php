<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['sometimes', 'string', 'min:3', 'max:100'],
            'require_approval' => ['sometimes', 'boolean'],
            'max_members'      => ['sometimes', 'nullable', 'integer', 'min:2'],
        ];
    }
}

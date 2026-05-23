<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_owner_id' => ['required', 'uuid', 'exists:users,id'],
        ];
    }
}

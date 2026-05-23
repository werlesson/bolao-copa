<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePushSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'endpoint'        => ['required', 'string', 'url'],
            'keys'            => ['required', 'array'],
            'keys.p256dh'     => ['required', 'string'],
            'keys.auth'       => ['required', 'string'],
        ];
    }
}

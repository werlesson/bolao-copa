<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'email'            => $this->email,
            'avatar_url'       => $this->avatar_url,
            'onboarding_done'  => $this->onboarding_done,
            'is_admin'         => $this->is_admin,
            'has_push'         => !empty($this->push_subscription),
        ];
    }
}

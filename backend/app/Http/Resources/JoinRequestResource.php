<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JoinRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'group_id'   => $this->group_id,
            'user_id'    => $this->user_id,
            'status'     => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'user'       => $this->whenLoaded('user', fn () => [
                'id'         => $this->user->id,
                'name'       => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ]),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authUser = $request->user();
        $isOwner  = $authUser?->id === $this->owner_id;

        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'slug'                   => $this->slug,
            'is_global'              => $this->is_global,
            'require_approval'       => $this->require_approval,
            'max_members'            => $this->max_members,
            'owner_id'               => $this->owner_id,
            'is_owner'               => $isOwner,
            // invite_token only visible to the group owner
            'invite_token'           => $this->when($isOwner, $this->invite_token),
            // populated via withCount / loadCount in the controller
            'members_count'          => $this->group_members_count ?? null,
            'user_rank'              => $this->when(
                array_key_exists('user_rank', $this->resource->getAttributes()),
                $this->user_rank,
            ),
            'pending_requests_count' => $this->when($isOwner, $this->pending_requests_count ?? 0),
            // only included when relation is explicitly eager-loaded (show endpoint)
            'members'                => $this->whenLoaded('members', fn () =>
                $this->members->map(fn ($u) => [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'avatar_url' => $u->avatar_url,
                    'joined_at'  => $u->pivot->joined_at,
                ])
            ),
            'created_at'             => $this->created_at?->toISOString(),
        ];
    }
}

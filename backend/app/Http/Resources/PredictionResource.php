<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PredictionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'match_id'      => $this->match_id,
            'user_id'       => $this->user_id,
            'home_score'    => $this->home_score,
            'away_score'    => $this->away_score,
            'points_earned' => $this->points_earned,
            'user'          => $this->whenLoaded('user', fn () => [
                'id'         => $this->user->id,
                'name'       => $this->user->name,
                'avatar_url' => $this->user->avatar_url,
            ]),
            'match'         => new MatchResource($this->whenLoaded('match')),
        ];
    }
}

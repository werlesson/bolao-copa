<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'external_id' => $this->external_id,
            'home_team'   => $this->home_team,
            'away_team'  => $this->away_team,
            'home_flag'  => $this->home_flag,
            'away_flag'  => $this->away_flag,
            'starts_at'  => $this->starts_at->toISOString(),
            'stage'      => $this->stage,
            'group_name' => $this->group_name,
            'status'     => $this->status,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'synced_at'  => $this->synced_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RankingBulletinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $match = $this->match;
        $score = ($match && $match->home_score !== null && $match->away_score !== null)
            ? "{$match->home_score}×{$match->away_score}"
            : '×';

        return [
            'id'         => $this->id,
            'content'    => $this->content,
            'source'     => $this->source,
            'created_at' => $this->created_at?->toISOString(),
            'match'      => $match ? [
                'id'        => $match->id,
                'label'     => "{$match->home_team} {$score} {$match->away_team}",
                'home_team' => $match->home_team,
                'away_team' => $match->away_team,
            ] : null,
        ];
    }
}

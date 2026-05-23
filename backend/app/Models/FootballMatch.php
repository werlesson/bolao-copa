<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'external_id', 'home_team', 'away_team', 'home_flag', 'away_flag',
    'starts_at', 'stage', 'group_name', 'status', 'home_score', 'away_score', 'synced_at',
])]
class FootballMatch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'matches';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'match_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'group_id', 'total_points', 'exact_scores', 'correct_results', 'total_predictions', 'last_position'])]
class Ranking extends Model
{
    use HasUuids;

    // rankings only has updated_at, no created_at
    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'last_position' => 'integer',
            'updated_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}

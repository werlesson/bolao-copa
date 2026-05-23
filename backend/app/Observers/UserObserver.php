<?php

namespace App\Observers;

use App\Models\GroupMember;
use App\Models\Ranking;
use App\Models\User;
use App\Models\Group;

class UserObserver
{
    public function created(User $user): void
    {
        $globalGroup = Group::where('is_global', true)->first();

        if (! $globalGroup) {
            return;
        }

        GroupMember::create([
            'group_id'  => $globalGroup->id,
            'user_id'   => $user->id,
            'joined_at' => now(),
        ]);

        Ranking::create([
            'user_id'          => $user->id,
            'group_id'         => $globalGroup->id,
            'total_points'     => 0,
            'exact_scores'     => 0,
            'correct_results'  => 0,
            'total_predictions'=> 0,
        ]);
    }
}

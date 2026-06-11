<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Ranking;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class AdjustWerlessonGroupSeeder extends Seeder
{
    private const MAIN_EMAIL = 'werlessono@gmail.com';

    private const MEMBER_COUNT = 20;

    public function run(): void
    {
        $user = User::where('email', self::MAIN_EMAIL)->firstOrFail();

        $group = Group::where('name', 'Bolão da Firma')->firstOrFail();
        $group->update(['owner_id' => $user->id]);

        $others = User::query()
            ->where('id', '!=', $user->id)
            ->whereNull('deactivated_at')
            ->inRandomOrder()
            ->limit(self::MEMBER_COUNT - 1)
            ->pluck('id');

        $keep = $others->push($user->id)->unique()->values();

        GroupMember::where('group_id', $group->id)
            ->whereNotIn('user_id', $keep)
            ->delete();

        Ranking::where('group_id', $group->id)
            ->whereNotIn('user_id', $keep)
            ->delete();

        foreach ($keep as $userId) {
            GroupMember::firstOrCreate(
                ['group_id' => $group->id, 'user_id' => $userId],
                ['joined_at' => now()->subDays(random_int(1, 30))],
            );

            Ranking::updateOrCreate(
                ['group_id' => $group->id, 'user_id' => $userId],
                [
                    'total_points'      => random_int(0, 42),
                    'exact_scores'      => random_int(0, 5),
                    'correct_results'   => random_int(0, 10),
                    'total_predictions' => random_int(5, 18),
                ],
            );
        }

        Cache::forget("ranking:group:{$group->id}");
        Cache::forget("ranking:group:{$group->id}:global");

        $this->command?->info("Grupo \"{$group->name}\" — dono: {$user->email} — membros: {$keep->count()}");
    }
}

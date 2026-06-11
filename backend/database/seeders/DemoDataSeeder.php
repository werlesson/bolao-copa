<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Ranking;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private const MAIN_EMAIL = 'werlessono@gmail.com';

    /** @var array<int, array{name: string, members: int}> */
    private const GROUPS = [
        ['name' => 'Bolão da Firma', 'members' => 47],
        ['name' => 'Churrascão FC', 'members' => 31],
        ['name' => 'Resenha do Zap', 'members' => 68],
        ['name' => 'Galera do Bar', 'members' => 82],
    ];

    public function run(): void
    {
        $this->call(GlobalGroupSeeder::class);

        $mainUser = User::firstOrCreate(
            ['email' => self::MAIN_EMAIL],
            [
                'name'            => 'Werlesson',
                'google_id'       => 'demo-werlessono',
                'avatar_url'      => null,
                'onboarding_done' => true,
                'is_admin'        => false,
            ],
        );

        $existingCount = User::count();
        $toCreate = max(0, 100 - $existingCount);

        if ($toCreate > 0) {
            User::factory($toCreate)->create();
        }

        /** @var Collection<int, User> $allUsers */
        $allUsers = User::active()->get();

        if ($allUsers->count() < 100) {
            User::factory(100 - $allUsers->count())->create();
            $allUsers = User::active()->get();
        }

        $globalGroup = Group::where('is_global', true)->firstOrFail();

        foreach ($allUsers as $user) {
            GroupMember::firstOrCreate(
                ['group_id' => $globalGroup->id, 'user_id' => $user->id],
                ['joined_at' => now()->subDays(random_int(1, 90))],
            );
            $this->upsertRanking($globalGroup->id, $user->id);
        }

        foreach (self::GROUPS as $index => $spec) {
            $this->seedGroup(
                name: $spec['name'],
                memberCount: min($spec['members'], $allUsers->count()),
                owner: $index === 0 ? $mainUser : $allUsers->random(),
                mainUser: $mainUser,
                pool: $allUsers,
            );
        }

        $this->command?->info(sprintf(
            'Demo data pronto: %d usuários, %d grupos (+%s em todos).',
            User::count(),
            count(self::GROUPS),
            self::MAIN_EMAIL,
        ));
    }

    /** @param Collection<int, User> $pool */
    private function seedGroup(
        string $name,
        int $memberCount,
        User $owner,
        User $mainUser,
        Collection $pool,
    ): void {
        $slug = $this->uniqueSlug($name);

        $group = Group::firstOrCreate(
            ['slug' => $slug],
            [
                'name'             => $name,
                'invite_token'     => Str::random(32),
                'owner_id'         => $owner->id,
                'is_global'        => false,
                'require_approval' => false,
                'max_members'      => null,
            ],
        );

        $memberIds = $pool->random(min($memberCount, $pool->count()))
            ->pluck('id')
            ->push($mainUser->id, $owner->id)
            ->unique()
            ->values();

        foreach ($memberIds as $userId) {
            GroupMember::firstOrCreate(
                ['group_id' => $group->id, 'user_id' => $userId],
                ['joined_at' => now()->subDays(random_int(1, 60))],
            );

            $this->upsertRanking($group->id, $userId);
        }
    }

    private function upsertRanking(string $groupId, string $userId): void
    {
        $totalPoints = random_int(0, 42);
        $exactScores = random_int(0, min(8, intdiv($totalPoints, 3)));
        $correctResults = random_int($exactScores, min(14, $exactScores + random_int(0, 6)));
        $totalPredictions = random_int(max($correctResults, 1), 18);

        Ranking::updateOrCreate(
            ['user_id' => $userId, 'group_id' => $groupId],
            [
                'total_points'      => $totalPoints,
                'exact_scores'      => $exactScores,
                'correct_results'   => $correctResults,
                'total_predictions' => $totalPredictions,
            ],
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;

        if (Group::where('slug', $slug)->exists()) {
            $slug = $base . '-demo';
        }

        return $slug;
    }
}

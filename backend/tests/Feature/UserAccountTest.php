<?php

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Prediction;
use App\Models\Ranking;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    public function test_user_stats_counts_all_predictions(): void
    {
        $user = User::factory()->create();
        $globalGroup = Group::where('is_global', true)->firstOrFail();

        $ranking = Ranking::where('user_id', $user->id)
            ->where('group_id', $globalGroup->id)
            ->firstOrFail();

        $ranking->update([
            'total_points'      => 3,
            'exact_scores'      => 1,
            'correct_results'   => 1,
            'total_predictions' => 1,
        ]);

        $scheduledMatch = FootballMatch::create([
            'external_id' => 1001,
            'home_team'   => 'Brasil',
            'away_team'   => 'Argentina',
            'starts_at'   => now()->addDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::SCHEDULED->value,
        ]);

        $finishedMatch = FootballMatch::create([
            'external_id' => 1002,
            'home_team'   => 'França',
            'away_team'   => 'Alemanha',
            'starts_at'   => now()->subDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::FINISHED->value,
            'home_score'  => 2,
            'away_score'  => 1,
        ]);

        Prediction::create([
            'user_id'       => $user->id,
            'match_id'      => $scheduledMatch->id,
            'home_score'    => 1,
            'away_score'    => 0,
            'points_earned' => null,
        ]);

        Prediction::create([
            'user_id'       => $user->id,
            'match_id'      => $finishedMatch->id,
            'home_score'    => 2,
            'away_score'    => 1,
            'points_earned' => 3,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user/stats');

        $response->assertOk()
            ->assertJsonPath('total_predictions', 2)
            ->assertJsonPath('total_points', 3)
            ->assertJsonPath('exact_scores', 1)
            ->assertJsonPath('accuracy_percent', 100);
    }

    public function test_user_stats_accuracy_is_points_earned_over_max_possible(): void
    {
        $user = User::factory()->create();
        $globalGroup = Group::where('is_global', true)->firstOrFail();

        Ranking::where('user_id', $user->id)
            ->where('group_id', $globalGroup->id)
            ->update([
                'total_points'      => 4,
                'exact_scores'      => 1,
                'correct_results'   => 2,
                'total_predictions' => 2,
            ]);

        $finishedMatches = collect([
            ['external_id' => 3001, 'home_team' => 'Brasil', 'away_team' => 'Argentina'],
            ['external_id' => 3002, 'home_team' => 'França', 'away_team' => 'Alemanha'],
            ['external_id' => 3003, 'home_team' => 'Espanha', 'away_team' => 'Portugal'],
        ])->map(function (array $data, int $index) {
            return FootballMatch::create([
                ...$data,
                'starts_at'  => now()->subDays($index + 1),
                'stage'      => 'GROUP_STAGE',
                'status'     => MatchStatus::FINISHED->value,
                'home_score' => 1,
                'away_score' => 0,
            ]);
        });

        Prediction::create([
            'user_id'       => $user->id,
            'match_id'      => $finishedMatches[0]->id,
            'home_score'    => 1,
            'away_score'    => 0,
            'points_earned' => 3,
        ]);

        Prediction::create([
            'user_id'       => $user->id,
            'match_id'      => $finishedMatches[1]->id,
            'home_score'    => 1,
            'away_score'    => 0,
            'points_earned' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/user/stats')
            ->assertOk()
            ->assertJsonPath('total_points', 4)
            ->assertJsonPath('accuracy_percent', 67);
    }

    public function test_deactivate_hides_user_from_ranking(): void
    {
        $active = User::factory()->create(['name' => 'Ativo']);
        $inactive = User::factory()->create(['name' => 'Inativo', 'deactivated_at' => now()]);

        $group = Group::create([
            'name'             => 'Grupo Teste',
            'slug'             => 'grupo-teste',
            'invite_token'     => Str::random(32),
            'owner_id'         => $active->id,
            'is_global'        => false,
            'require_approval' => false,
            'max_members'      => null,
        ]);

        foreach ([$active, $inactive] as $member) {
            GroupMember::create([
                'group_id'  => $group->id,
                'user_id'   => $member->id,
                'joined_at' => now(),
            ]);

            Ranking::create([
                'group_id'          => $group->id,
                'user_id'           => $member->id,
                'total_points'      => 5,
                'exact_scores'      => 0,
                'correct_results'   => 1,
                'total_predictions' => 1,
            ]);
        }

        Cache::flush();
        Sanctum::actingAs($active);

        $response = $this->getJson("/api/groups/{$group->id}/ranking");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('user.name');
        $this->assertTrue($names->contains('Ativo'));
        $this->assertFalse($names->contains('Inativo'));
    }

    public function test_deactivate_revokes_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user/deactivate');

        $response->assertOk();
        $this->assertNotNull($user->fresh()->deactivated_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_delete_user_removes_data(): void
    {
        $user = User::factory()->create();

        $match = FootballMatch::create([
            'external_id' => 2001,
            'home_team'   => 'Brasil',
            'away_team'   => 'Chile',
            'starts_at'   => now()->addDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::SCHEDULED->value,
        ]);

        Prediction::create([
            'user_id'    => $user->id,
            'match_id'   => $match->id,
            'home_score' => 1,
            'away_score' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/user');

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('predictions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('group_members', ['user_id' => $user->id]);
    }
}

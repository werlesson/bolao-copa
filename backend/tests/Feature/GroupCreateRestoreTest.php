<?php

namespace Tests\Feature;

use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\Prediction;
use App\Models\Ranking;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupCreateRestoreTest extends TestCase
{
    public function test_create_group_restores_owner_historical_points(): void
    {
        $owner = User::factory()->create();

        $finishedMatch = FootballMatch::create([
            'external_id' => 9001,
            'home_team'   => 'Brasil',
            'away_team'   => 'Argentina',
            'starts_at'   => now()->subDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::FINISHED->value,
            'home_score'  => 2,
            'away_score'  => 1,
        ]);

        Prediction::create([
            'user_id'       => $owner->id,
            'match_id'      => $finishedMatch->id,
            'home_score'    => 2,
            'away_score'    => 1,
            'points_earned' => 3,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/groups', [
            'name'             => 'Grupo Restaurado',
            'require_approval' => false,
        ]);

        $response->assertCreated();

        $groupId = $response->json('data.id');

        $this->assertDatabaseHas('rankings', [
            'group_id'          => $groupId,
            'user_id'           => $owner->id,
            'total_points'      => 3,
            'exact_scores'      => 1,
            'correct_results'   => 1,
            'total_predictions' => 1,
        ]);
    }

    public function test_join_group_also_restores_historical_points(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $group = Group::create([
            'name'             => 'Grupo Existente',
            'slug'             => 'grupo-existente-' . Str::random(6),
            'invite_token'     => Str::random(32),
            'owner_id'         => $owner->id,
            'is_global'        => false,
            'require_approval' => false,
            'max_members'      => null,
        ]);

        $finishedMatch = FootballMatch::create([
            'external_id' => 9002,
            'home_team'   => 'França',
            'away_team'   => 'Alemanha',
            'starts_at'   => now()->subDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::FINISHED->value,
            'home_score'  => 1,
            'away_score'  => 0,
        ]);

        Prediction::create([
            'user_id'       => $member->id,
            'match_id'      => $finishedMatch->id,
            'home_score'    => 1,
            'away_score'    => 0,
            'points_earned' => 3,
        ]);

        Sanctum::actingAs($member);

        $this->postJson("/api/groups/join/{$group->invite_token}")
            ->assertCreated();

        $this->assertDatabaseHas('rankings', [
            'group_id'          => $group->id,
            'user_id'           => $member->id,
            'total_points'      => 3,
            'exact_scores'      => 1,
            'correct_results'   => 1,
            'total_predictions' => 1,
        ]);
    }
}

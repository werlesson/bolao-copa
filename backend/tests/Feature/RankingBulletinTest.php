<?php

namespace Tests\Feature;

use App\Contracts\RankingBulletinGenerator;
use App\Enums\MatchStatus;
use App\Jobs\GenerateRankingBulletin;
use App\Jobs\RecalculateRankings;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Prediction;
use App\Models\Ranking;
use App\Models\RankingBulletin;
use App\Models\User;
use App\Services\BulletinContentValidator;
use App\Services\RankingMovementService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RankingBulletinTest extends TestCase
{
    public function test_recalculate_dispatches_bulletin_job_and_creates_template(): void
    {
        Queue::fake([GenerateRankingBulletin::class]);

        $user = User::factory()->create();
        $group = Group::create([
            'name'             => 'Trabalho',
            'slug'             => 'trabalho-' . Str::random(6),
            'invite_token'     => Str::random(32),
            'owner_id'         => $user->id,
            'is_global'        => false,
            'require_approval' => false,
            'max_members'      => null,
        ]);

        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $user->id,
            'joined_at' => now(),
        ]);

        $match = FootballMatch::create([
            'external_id' => 91001,
            'home_team'   => 'Brasil',
            'away_team'   => 'Chile',
            'starts_at'   => now()->subDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::FINISHED->value,
            'home_score'  => 2,
            'away_score'  => 0,
        ]);

        Prediction::create([
            'user_id'       => $user->id,
            'match_id'      => $match->id,
            'home_score'    => 2,
            'away_score'    => 0,
            'points_earned' => 3,
        ]);

        Ranking::create([
            'user_id'           => $user->id,
            'group_id'          => $group->id,
            'total_points'      => 0,
            'exact_scores'      => 0,
            'correct_results'   => 0,
            'total_predictions' => 0,
        ]);

        RecalculateRankings::dispatchSync($match->id);

        Queue::assertPushed(GenerateRankingBulletin::class, function (GenerateRankingBulletin $job) use ($group, $match) {
            return $job->groupId === $group->id && $job->matchId === $match->id;
        });

        $job = new GenerateRankingBulletin(
            $group->id,
            $match->id,
            [ $user->id => 1 ],
            [ $user->id => 1 ],
        );
        $job->handle(
            app(RankingMovementService::class),
            app(RankingBulletinGenerator::class),
            app(BulletinContentValidator::class),
        );

        $this->assertDatabaseHas('ranking_bulletins', [
            'group_id' => $group->id,
            'match_id' => $match->id,
            'source'   => 'template',
        ]);
    }

    public function test_group_bulletin_requires_membership(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $group = Group::create([
            'name'             => 'Privado',
            'slug'             => 'privado-' . Str::random(6),
            'invite_token'     => Str::random(32),
            'owner_id'         => $owner->id,
            'is_global'        => false,
            'require_approval' => false,
            'max_members'      => null,
        ]);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/groups/{$group->id}/ranking/bulletin")
            ->assertForbidden();
    }

    public function test_member_can_fetch_group_bulletin(): void
    {
        $user = User::factory()->create();
        $group = Group::create([
            'name'             => 'Família',
            'slug'             => 'familia-' . Str::random(6),
            'invite_token'     => Str::random(32),
            'owner_id'         => $user->id,
            'is_global'        => false,
            'require_approval' => false,
            'max_members'      => null,
        ]);

        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $user->id,
            'joined_at' => now(),
        ]);

        $match = FootballMatch::create([
            'external_id' => 91002,
            'home_team'   => 'Espanha',
            'away_team'   => 'Itália',
            'starts_at'   => now()->subDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::FINISHED->value,
            'home_score'  => 1,
            'away_score'  => 0,
        ]);

        RankingBulletin::create([
            'group_id'          => $group->id,
            'match_id'          => $match->id,
            'content'           => 'Espanha 1×0 Itália — teve gol, teve ponto.',
            'source'            => 'template',
            'movement_summary'  => null,
            'created_at'        => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/groups/{$group->id}/ranking/bulletin")
            ->assertOk()
            ->assertJsonPath('data.0.content', 'Espanha 1×0 Itália — teve gol, teve ponto.');
    }

    public function test_uses_gemini_when_significant_and_ai_enabled(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => 'Ana passou na frente e agora manda no Trabalho! Bruno perdeu a ponta.',
                        ]],
                    ],
                ]],
            ]),
        ]);

        config([
            'services.ai_ranking.enabled' => true,
            'services.gemini.api_key'     => 'test-key',
        ]);

        $leader = User::factory()->create(['name' => 'Ana']);
        $challenger = User::factory()->create(['name' => 'Bruno']);

        $group = Group::create([
            'name'             => 'Trabalho',
            'slug'             => 'trabalho-ai-' . Str::random(6),
            'invite_token'     => Str::random(32),
            'owner_id'         => $leader->id,
            'is_global'        => false,
            'require_approval' => false,
            'max_members'      => null,
        ]);

        foreach ([$leader, $challenger] as $user) {
            GroupMember::create([
                'group_id'  => $group->id,
                'user_id'   => $user->id,
                'joined_at' => now(),
            ]);

            Ranking::create([
                'user_id'           => $user->id,
                'group_id'          => $group->id,
                'total_points'      => 0,
                'exact_scores'      => 0,
                'correct_results'   => 0,
                'total_predictions' => 0,
            ]);
        }

        $match = FootballMatch::create([
            'external_id' => 91003,
            'home_team'   => 'Brasil',
            'away_team'   => 'Chile',
            'starts_at'   => now()->subDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::FINISHED->value,
            'home_score'  => 2,
            'away_score'  => 0,
        ]);

        Prediction::create([
            'user_id'       => $leader->id,
            'match_id'      => $match->id,
            'home_score'    => 2,
            'away_score'    => 0,
            'points_earned' => 3,
        ]);

        Prediction::create([
            'user_id'       => $challenger->id,
            'match_id'      => $match->id,
            'home_score'    => 2,
            'away_score'    => 1,
            'points_earned' => 1,
        ]);

        $job = new GenerateRankingBulletin(
            $group->id,
            $match->id,
            [$leader->id => 2, $challenger->id => 1],
            [$leader->id => 1, $challenger->id => 2],
        );

        $job->handle(
            app(RankingMovementService::class),
            app(RankingBulletinGenerator::class),
            app(BulletinContentValidator::class),
        );

        $this->assertDatabaseHas('ranking_bulletins', [
            'group_id' => $group->id,
            'match_id' => $match->id,
            'source'   => 'ai',
        ]);

        Http::assertSentCount(1);
    }

    public function test_falls_back_to_template_when_ai_response_is_invalid(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => 'Texto inventado sem placar nem times reais.',
                        ]],
                    ],
                ]],
            ]),
        ]);

        config([
            'services.ai_ranking.enabled' => true,
            'services.gemini.api_key'     => 'test-key',
        ]);

        $leader = User::factory()->create(['name' => 'Carla']);
        $challenger = User::factory()->create(['name' => 'Diego']);

        $group = Group::create([
            'name'             => 'Família',
            'slug'             => 'familia-ai-' . Str::random(6),
            'invite_token'     => Str::random(32),
            'owner_id'         => $leader->id,
            'is_global'        => false,
            'require_approval' => false,
            'max_members'      => null,
        ]);

        foreach ([$leader, $challenger] as $user) {
            GroupMember::create([
                'group_id'  => $group->id,
                'user_id'   => $user->id,
                'joined_at' => now(),
            ]);
        }

        $match = FootballMatch::create([
            'external_id' => 91004,
            'home_team'   => 'França',
            'away_team'   => 'Itália',
            'starts_at'   => now()->subDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::FINISHED->value,
            'home_score'  => 1,
            'away_score'  => 0,
        ]);

        Prediction::create([
            'user_id'       => $leader->id,
            'match_id'      => $match->id,
            'home_score'    => 1,
            'away_score'    => 0,
            'points_earned' => 3,
        ]);

        Prediction::create([
            'user_id'       => $challenger->id,
            'match_id'      => $match->id,
            'home_score'    => 0,
            'away_score'    => 0,
            'points_earned' => 0,
        ]);

        $job = new GenerateRankingBulletin(
            $group->id,
            $match->id,
            [$leader->id => 2, $challenger->id => 1],
            [$leader->id => 1, $challenger->id => 2],
        );

        $job->handle(
            app(RankingMovementService::class),
            app(RankingBulletinGenerator::class),
            app(BulletinContentValidator::class),
        );

        $this->assertDatabaseHas('ranking_bulletins', [
            'group_id' => $group->id,
            'match_id' => $match->id,
            'source'   => 'template',
        ]);
    }

    public function test_authenticated_user_can_fetch_global_bulletin(): void
    {
        $user = User::factory()->create();
        $global = Group::where('is_global', true)->firstOrFail();

        GroupMember::firstOrCreate(
            ['group_id' => $global->id, 'user_id' => $user->id],
            ['joined_at' => now()],
        );

        $match = FootballMatch::create([
            'external_id' => 91005,
            'home_team'   => 'Canada',
            'away_team'   => 'Bosnia-H.',
            'starts_at'   => now()->subDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::FINISHED->value,
            'home_score'  => 1,
            'away_score'  => 1,
        ]);

        RankingBulletin::create([
            'group_id'         => $global->id,
            'match_id'         => $match->id,
            'content'          => 'Mexeu geral no Geral Copa 2026!',
            'source'           => 'ai',
            'movement_summary' => null,
            'created_at'       => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/rankings/global/bulletin')
            ->assertOk()
            ->assertJsonPath('data.0.content', 'Mexeu geral no Geral Copa 2026!')
            ->assertJsonPath('data.0.source', 'ai');
    }

    public function test_stable_ranking_does_not_call_gemini(): void
    {
        Http::fake();

        config([
            'services.ai_ranking.enabled' => true,
            'services.gemini.api_key'     => 'test-key',
        ]);

        $user = User::factory()->create(['name' => 'Lucas']);
        $group = Group::create([
            'name'             => 'Zera Movimento',
            'slug'             => 'zera-' . Str::random(6),
            'invite_token'     => Str::random(32),
            'owner_id'         => $user->id,
            'is_global'        => false,
            'require_approval' => false,
            'max_members'      => null,
        ]);

        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $user->id,
            'joined_at' => now(),
        ]);

        $match = FootballMatch::create([
            'external_id' => 91006,
            'home_team'   => 'Espanha',
            'away_team'   => 'Itália',
            'starts_at'   => now()->subDay(),
            'stage'       => 'GROUP_STAGE',
            'status'      => MatchStatus::FINISHED->value,
            'home_score'  => 1,
            'away_score'  => 0,
        ]);

        Prediction::create([
            'user_id'       => $user->id,
            'match_id'      => $match->id,
            'home_score'    => 1,
            'away_score'    => 0,
            'points_earned' => 3,
        ]);

        $job = new GenerateRankingBulletin(
            $group->id,
            $match->id,
            [$user->id => 1],
            [$user->id => 1],
        );

        $job->handle(
            app(RankingMovementService::class),
            app(RankingBulletinGenerator::class),
            app(BulletinContentValidator::class),
        );

        Http::assertNothingSent();

        $this->assertDatabaseHas('ranking_bulletins', [
            'group_id' => $group->id,
            'match_id' => $match->id,
            'source'   => 'template',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Ranking;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupUpdateTest extends TestCase
{
    public function test_owner_can_rename_group(): void
    {
        $owner = User::factory()->create();
        $group = Group::create([
            'name'             => 'Grupo Original',
            'slug'             => 'grupo-original',
            'invite_token'     => 'token-original',
            'owner_id'         => $owner->id,
            'is_global'        => false,
            'require_approval' => false,
        ]);
        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $owner->id,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/groups/{$group->id}", ['name' => 'Novo Nome'])
            ->assertOk()
            ->assertJsonPath('name', 'Novo Nome')
            ->assertJsonPath('slug', 'novo-nome');

        $this->assertDatabaseHas('groups', [
            'id'   => $group->id,
            'name' => 'Novo Nome',
            'slug' => 'novo-nome',
        ]);
    }

    public function test_non_owner_cannot_rename_group(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create([
            'name'             => 'Grupo Original',
            'slug'             => 'grupo-original-2',
            'invite_token'     => 'token-original-2',
            'owner_id'         => $owner->id,
            'is_global'        => false,
            'require_approval' => false,
        ]);
        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $member->id,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $this->patchJson("/api/groups/{$group->id}", ['name' => 'Tentativa'])
            ->assertForbidden();

        $this->assertDatabaseHas('groups', [
            'id'   => $group->id,
            'name' => 'Grupo Original',
        ]);
    }

    public function test_groups_index_includes_user_rank(): void
    {
        $owner  = User::factory()->create();
        $rival  = User::factory()->create();
        $group  = Group::create([
            'name'             => 'Ranking Teste',
            'slug'             => 'ranking-teste',
            'invite_token'     => 'token-ranking',
            'owner_id'         => $owner->id,
            'is_global'        => false,
            'require_approval' => false,
        ]);

        foreach ([$owner, $rival] as $user) {
            GroupMember::create([
                'group_id'  => $group->id,
                'user_id'   => $user->id,
                'joined_at' => now(),
            ]);
        }

        Ranking::create([
            'group_id'          => $group->id,
            'user_id'           => $rival->id,
            'total_points'      => 10,
            'exact_scores'      => 2,
            'correct_results'   => 3,
            'total_predictions' => 3,
        ]);
        Ranking::create([
            'group_id'          => $group->id,
            'user_id'           => $owner->id,
            'total_points'      => 3,
            'exact_scores'      => 1,
            'correct_results'   => 1,
            'total_predictions' => 1,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/groups')
            ->assertOk()
            ->assertJsonFragment([
                'id'        => $group->id,
                'user_rank' => 2,
            ]);
    }
}

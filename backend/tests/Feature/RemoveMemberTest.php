<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupBan;
use App\Models\GroupMember;
use App\Models\Ranking;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemoveMemberTest extends TestCase
{
    private function createGroupWithOwnerAndMember(): array
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $group  = Group::create([
            'name'             => 'Grupo Teste',
            'slug'             => 'grupo-teste-' . uniqid(),
            'invite_token'     => 'token-' . uniqid(),
            'owner_id'         => $owner->id,
            'is_global'        => false,
            'require_approval' => false,
        ]);

        foreach ([$owner, $member] as $user) {
            GroupMember::create([
                'group_id'  => $group->id,
                'user_id'   => $user->id,
                'joined_at' => now(),
            ]);
            Ranking::create([
                'group_id'          => $group->id,
                'user_id'           => $user->id,
                'total_points'      => 0,
                'exact_scores'      => 0,
                'correct_results'   => 0,
                'total_predictions' => 0,
            ]);
        }

        return compact('owner', 'member', 'group');
    }

    public function test_owner_can_remove_member(): void
    {
        ['owner' => $owner, 'member' => $member, 'group' => $group] = $this->createGroupWithOwnerAndMember();

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/groups/{$group->id}/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Membro removido e banido do grupo.');

        $this->assertDatabaseMissing('group_members', [
            'group_id' => $group->id,
            'user_id'  => $member->id,
        ]);
        $this->assertDatabaseHas('group_bans', [
            'group_id' => $group->id,
            'user_id'  => $member->id,
            'banned_by' => $owner->id,
        ]);
        $this->assertDatabaseMissing('rankings', [
            'group_id' => $group->id,
            'user_id'  => $member->id,
        ]);
    }

    public function test_non_owner_cannot_remove_member(): void
    {
        ['member' => $member, 'group' => $group] = $this->createGroupWithOwnerAndMember();
        $other = User::factory()->create();

        Sanctum::actingAs($other);

        $this->deleteJson("/api/groups/{$group->id}/members/{$member->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id'  => $member->id,
        ]);
    }

    public function test_owner_cannot_remove_self(): void
    {
        ['owner' => $owner, 'group' => $group] = $this->createGroupWithOwnerAndMember();

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/groups/{$group->id}/members/{$owner->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id'  => $owner->id,
        ]);
    }

    public function test_cannot_remove_member_from_global_group(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $global = Group::where('is_global', true)->firstOrFail();

        GroupMember::firstOrCreate([
            'group_id' => $global->id,
            'user_id'  => $member->id,
        ], ['joined_at' => now()]);

        $global->update(['owner_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/groups/{$global->id}/members/{$member->id}")
            ->assertForbidden();
    }

    public function test_remove_non_member_returns_404(): void
    {
        ['owner' => $owner, 'group' => $group] = $this->createGroupWithOwnerAndMember();
        $stranger = User::factory()->create();

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/groups/{$group->id}/members/{$stranger->id}")
            ->assertNotFound();
    }
}

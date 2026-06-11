<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\TransferGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Http\Resources\JoinRequestResource;
use App\Jobs\RecalculateRankings;
use App\Models\Group;
use App\Services\RankingPositionService;
use App\Models\GroupBan;
use App\Models\GroupJoinRequest;
use App\Models\GroupMember;
use App\Models\Prediction;
use App\Models\Ranking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    // ─── Group CRUD ───────────────────────────────────────────────────────────

    public function index(Request $request, RankingPositionService $rankingPositions): AnonymousResourceCollection
    {
        $groups = $request->user()
            ->groups()
            ->withCount($this->activeMemberCountConstraint())
            ->withCount(['joinRequests as pending_requests_count' => fn ($q) => $q->where('status', 'PENDING')])
            ->get();

        $positions = $rankingPositions->userPositionsByGroup(
            $request->user()->id,
            $groups->pluck('id')->all(),
        );

        $groups->each(function (Group $group) use ($positions) {
            $group->setAttribute('user_rank', $positions[$group->id] ?? null);
        });

        return GroupResource::collection($groups);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $slug = $this->uniqueSlug($data['name']);

        $group = DB::transaction(function () use ($user, $data, $slug) {
            $group = Group::create([
                'name'             => $data['name'],
                'slug'             => $slug,
                'invite_token'     => Str::random(32),
                'owner_id'         => $user->id,
                'is_global'        => false,
                'require_approval' => $data['require_approval'] ?? false,
                'max_members'      => $data['max_members'] ?? null,
            ]);

            GroupMember::create([
                'group_id'  => $group->id,
                'user_id'   => $user->id,
                'joined_at' => now(),
            ]);

            Ranking::create([
                'group_id'         => $group->id,
                'user_id'          => $user->id,
                'total_points'     => 0,
                'exact_scores'     => 0,
                'correct_results'  => 0,
                'total_predictions'=> 0,
            ]);

            return $group;
        });

        $this->loadActiveMemberCount($group);

        return (new GroupResource($group))->response()->setStatusCode(201);
    }

    public function show(Group $group, Request $request): GroupResource
    {
        $this->loadActiveMemberCount($group);
        $group->loadCount(['joinRequests as pending_requests_count' => fn ($q) => $q->where('status', 'PENDING')])
            ->load(['members' => fn ($q) => $q->whereNull('users.deactivated_at')]);

        return new GroupResource($group);
    }

    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if ($group->owner_id !== $user->id) {
            return response()->json(['message' => 'Apenas o dono pode editar o grupo.'], 403);
        }

        if ($group->is_global && $request->boolean('require_approval')) {
            return response()->json(['message' => 'O grupo global não pode exigir aprovação.'], 403);
        }

        $data = $request->validated();

        if (array_key_exists('name', $data) && $data['name'] !== $group->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $group->id);
        }

        $group->update($data);
        $this->loadActiveMemberCount($group);

        return response()->json(new GroupResource($group));
    }

    public function destroy(Group $group, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($group->is_global) {
            return response()->json(['message' => 'O grupo global não pode ser excluído.'], 403);
        }

        if ($group->owner_id !== $user->id) {
            return response()->json(['message' => 'Apenas o dono pode excluir o grupo.'], 403);
        }

        $this->invalidateGroupRankingCache($group->id);
        $group->delete();

        return response()->json(['message' => 'Grupo excluído com sucesso.']);
    }

    // ─── Membership ──────────────────────────────────────────────────────────

    public function invitePreview(string $token, Request $request): JsonResponse
    {
        $user  = $request->user();
        $group = Group::where('invite_token', $token)->firstOrFail();

        $isMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->exists();

        $hasPendingRequest = GroupJoinRequest::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 'PENDING')
            ->exists();

        return response()->json([
            'id'                  => $group->id,
            'name'                => $group->name,
            'require_approval'    => $group->require_approval,
            'is_member'           => $isMember,
            'has_pending_request' => $hasPendingRequest,
        ]);
    }

    public function join(string $token, Request $request): JsonResponse
    {
        $user  = $request->user();
        $group = Group::where('invite_token', $token)->firstOrFail();

        // Banned users cannot rejoin
        if (GroupBan::where('group_id', $group->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Você não pode entrar neste grupo.', 'error_code' => 'USER_BANNED'], 403);
        }

        // Already a member
        if (GroupMember::where('group_id', $group->id)->where('user_id', $user->id)->exists()) {
            $this->loadActiveMemberCount($group);
            return response()->json(new GroupResource($group));
        }

        // Group capacity check — total members (including deactivated) occupy slots
        $totalMembers = GroupMember::where('group_id', $group->id)->count();
        if ($group->max_members !== null && $totalMembers >= $group->max_members) {
            return response()->json(['message' => 'O grupo está cheio.', 'error_code' => 'GROUP_FULL'], 422);
        }

        if ($group->require_approval) {
            return $this->handleJoinRequest($group, $user);
        }

        return $this->addMemberAndRestoreRanking($group, $user, 201);
    }

    public function leave(Group $group, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($group->is_global) {
            return response()->json(['message' => 'Você não pode sair do grupo global.'], 403);
        }

        $member = GroupMember::where('group_id', $group->id)->where('user_id', $user->id)->first();
        if (!$member) {
            return response()->json(['message' => 'Você não é membro deste grupo.'], 404);
        }

        DB::transaction(function () use ($group, $user, $member) {
            $member->delete();
            Ranking::where('group_id', $group->id)->where('user_id', $user->id)->delete();
            $this->invalidateGroupRankingCache($group->id);
        });

        return response()->json(['message' => 'Você saiu do grupo.']);
    }

    public function removeMember(Group $group, string $userId, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($group->is_global) {
            return response()->json(['message' => 'Não é possível remover membros do grupo global.'], 403);
        }

        if ($group->owner_id !== $user->id) {
            return response()->json(['message' => 'Apenas o dono pode remover membros.'], 403);
        }

        if ($userId === $user->id) {
            return response()->json(['message' => 'O dono não pode remover a si mesmo.'], 422);
        }

        if (! User::whereKey($userId)->exists()) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        $member = GroupMember::where('group_id', $group->id)->where('user_id', $userId)->first();
        if (!$member) {
            return response()->json(['message' => 'Usuário não é membro deste grupo.'], 404);
        }

        DB::transaction(function () use ($group, $userId, $user, $member) {
            GroupBan::firstOrCreate(
                ['group_id' => $group->id, 'user_id' => $userId],
                ['banned_by' => $user->id]
            );
            $member->delete();
            Ranking::where('group_id', $group->id)->where('user_id', $userId)->delete();
            $this->invalidateGroupRankingCache($group->id);
        });

        return response()->json(['message' => 'Membro removido e banido do grupo.']);
    }

    // ─── Ownership ───────────────────────────────────────────────────────────

    public function transfer(TransferGroupRequest $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if ($group->is_global) {
            return response()->json(['message' => 'O grupo global não pode ter ownership transferido.'], 403);
        }

        if ($group->owner_id !== $user->id) {
            return response()->json(['message' => 'Apenas o dono pode transferir o grupo.'], 403);
        }

        $newOwnerId = $request->validated('new_owner_id');

        if (!GroupMember::where('group_id', $group->id)->where('user_id', $newOwnerId)->exists()) {
            return response()->json(['message' => 'O novo dono deve ser membro do grupo.'], 422);
        }

        $group->update(['owner_id' => $newOwnerId]);

        return response()->json(['message' => 'Ownership transferido com sucesso.']);
    }

    public function regenerateInvite(Group $group, Request $request): JsonResponse
    {
        if ($group->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Apenas o dono pode regenerar o link de convite.'], 403);
        }

        $group->update(['invite_token' => Str::random(32)]);

        return response()->json(['invite_token' => $group->invite_token]);
    }

    // ─── Join Requests ────────────────────────────────────────────────────────

    public function requests(Group $group, Request $request): AnonymousResourceCollection
    {
        if ($group->owner_id !== $request->user()->id) {
            abort(403, 'Apenas o dono pode ver as solicitações.');
        }

        $pending = $group->joinRequests()
            ->where('status', 'PENDING')
            ->with('user')
            ->latest()
            ->get();

        return JoinRequestResource::collection($pending);
    }

    public function approveRequest(Group $group, GroupJoinRequest $joinRequest, Request $request): JsonResponse
    {
        if ($group->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Apenas o dono pode aprovar solicitações.'], 403);
        }

        if ($joinRequest->group_id !== $group->id) {
            return response()->json(['message' => 'Solicitação não pertence a este grupo.'], 404);
        }

        if ($joinRequest->status !== 'PENDING') {
            return response()->json(['message' => 'Apenas solicitações pendentes podem ser aprovadas.'], 422);
        }

        $groupFull = false;
        DB::transaction(function () use ($group, $joinRequest, &$groupFull) {
            // Lock the group row to serialize concurrent approvals and prevent exceeding max_members
            Group::where('id', $group->id)->lockForUpdate()->first();

            $currentCount = GroupMember::where('group_id', $group->id)->count();
            if ($group->max_members !== null && $currentCount >= $group->max_members) {
                $groupFull = true;
                return;
            }

            $joinRequest->update(['status' => 'APPROVED']);
            $this->addMemberAndRestoreRanking($group, $joinRequest->user);
        });

        if ($groupFull) {
            return response()->json(['message' => 'O grupo está cheio.', 'error_code' => 'GROUP_FULL'], 422);
        }

        return response()->json(['message' => 'Solicitação aprovada.']);
    }

    public function rejectRequest(Group $group, GroupJoinRequest $joinRequest, Request $request): JsonResponse
    {
        if ($group->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Apenas o dono pode rejeitar solicitações.'], 403);
        }

        if ($joinRequest->group_id !== $group->id) {
            return response()->json(['message' => 'Solicitação não pertence a este grupo.'], 404);
        }

        if ($joinRequest->status !== 'PENDING') {
            return response()->json(['message' => 'Apenas solicitações pendentes podem ser rejeitadas.'], 422);
        }

        $joinRequest->update(['status' => 'REJECTED']);

        return response()->json(['message' => 'Solicitação rejeitada.']);
    }

    public function cancelRequest(Group $group, GroupJoinRequest $joinRequest, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($joinRequest->group_id !== $group->id) {
            return response()->json(['message' => 'Solicitação não pertence a este grupo.'], 404);
        }

        if ($joinRequest->user_id !== $user->id) {
            return response()->json(['message' => 'Você não pode cancelar esta solicitação.'], 403);
        }

        if ($joinRequest->status !== 'PENDING') {
            return response()->json(['message' => 'Apenas solicitações pendentes podem ser canceladas.'], 422);
        }

        $joinRequest->update(['status' => 'CANCELLED']);

        return response()->json(['message' => 'Solicitação cancelada.']);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function handleJoinRequest(Group $group, User $user): JsonResponse
    {
        $existing = GroupJoinRequest::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->status === 'PENDING') {
                return response()->json(
                    ['message' => 'Você já tem uma solicitação pendente.', 'data' => new JoinRequestResource($existing)],
                    409
                );
            }
            // REJECTED or CANCELLED → reset to PENDING
            $existing->update(['status' => 'PENDING', 'updated_at' => now()]);
            return response()->json(new JoinRequestResource($existing), 202);
        }

        $joinRequest = GroupJoinRequest::create([
            'group_id' => $group->id,
            'user_id'  => $user->id,
            'status'   => 'PENDING',
        ]);

        return response()->json(new JoinRequestResource($joinRequest), 202);
    }

    /**
     * Creates GroupMember + Ranking (restoring historical points from existing predictions).
     * Invalidates the group's Redis ranking cache.
     * Returns a JsonResponse when called from join(); returns void when called internally from approveRequest().
     */
    private function addMemberAndRestoreRanking(Group $group, User $user, ?int $statusCode = null): JsonResponse|null
    {
        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $user->id,
            'joined_at' => now(),
        ]);

        // Restore historical ranking from existing finished-match predictions
        $stats = Prediction::query()
            ->join('matches', 'matches.id', '=', 'predictions.match_id')
            ->where('matches.status', MatchStatus::FINISHED->value)
            ->where('predictions.user_id', $user->id)
            ->selectRaw('
                COALESCE(SUM(predictions.points_earned), 0)::int         AS total_points,
                COUNT(*) FILTER (WHERE predictions.points_earned = 3)::int  AS exact_scores,
                COUNT(*) FILTER (WHERE predictions.points_earned >= 1)::int AS correct_results,
                COUNT(*) FILTER (WHERE predictions.points_earned IS NOT NULL)::int AS total_predictions
            ')
            ->first();

        Ranking::updateOrCreate(
            ['user_id' => $user->id, 'group_id' => $group->id],
            [
                'total_points'      => $stats?->total_points      ?? 0,
                'exact_scores'      => $stats?->exact_scores      ?? 0,
                'correct_results'   => $stats?->correct_results   ?? 0,
                'total_predictions' => $stats?->total_predictions ?? 0,
            ]
        );

        $this->invalidateGroupRankingCache($group->id);

        if ($statusCode === null) {
            return null;
        }

        $this->loadActiveMemberCount($group);
        return (new GroupResource($group))->response()->setStatusCode($statusCode);
    }

    /** @return array<string, \Closure> */
    private function activeMemberCountConstraint(): array
    {
        return [
            'groupMembers as group_members_count' => fn ($q) =>
                $q->whereHas('user', fn ($uq) => $uq->active()),
        ];
    }

    private function loadActiveMemberCount(Group $group): void
    {
        $group->loadCount($this->activeMemberCountConstraint());
    }

    private function uniqueSlug(string $name, ?string $exceptGroupId = null): string
    {
        $base = Str::slug($name) ?: 'grupo';
        $slug = $base;

        $slugTaken = fn (string $candidate) => Group::where('slug', $candidate)
            ->when($exceptGroupId, fn ($q) => $q->where('id', '!=', $exceptGroupId))
            ->exists();

        if ($slugTaken($slug)) {
            do {
                $slug = $base . '-' . Str::lower(Str::random(4));
            } while ($slugTaken($slug));
        }

        return $slug;
    }

    private function invalidateGroupRankingCache(string $groupId): void
    {
        Cache::forget("ranking:group:{$groupId}");
        Cache::forget("ranking:group:{$groupId}:global");
    }
}

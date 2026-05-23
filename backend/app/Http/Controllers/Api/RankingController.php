<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Ranking;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RankingController extends Controller
{
    public function groupRanking(Group $group): JsonResponse
    {
        return response()->json(['data' => $this->buildRanking($group->id)]);
    }

    public function globalRanking(Request $request): JsonResponse
    {
        $globalId = Cache::rememberForever('group:global:id', fn () =>
            Group::where('is_global', true)->firstOrFail()->id
        );

        [$entries, $total] = $this->buildGlobalRanking($globalId);

        // Always include the authenticated user's entry even when outside top 100.
        $userId    = $request->user()->id;
        $userEntry = collect($entries)->firstWhere('user.id', $userId);

        return response()->json([
            'data'       => $entries,
            'total'      => $total,
            'user_entry' => $userEntry,
        ]);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /**
     * Group ranking: all members, full list, 90 s Redis cache.
     * Cache key: "ranking:group:{id}"  (also used by GroupController::Cache::forget)
     */
    private function buildRanking(string $groupId): array
    {
        $cacheKey = "ranking:group:{$groupId}";
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        try {
            return Cache::lock("lock:{$cacheKey}", 10)
                ->block(5, function () use ($groupId, $cacheKey) {
                    if ($fresh = Cache::get($cacheKey)) return $fresh;
                    $data = $this->fetchRows($groupId, null);
                    Cache::put($cacheKey, $data, 90);
                    return $data;
                });
        } catch (LockTimeoutException) {
            return $this->fetchRows($groupId, null);
        }
    }

    /**
     * Global ranking: top-100 entries + total participant count, 90 s Redis cache.
     * Cache key: "ranking:group:{id}:global"
     * Returns [$entries, $total].
     */
    private function buildGlobalRanking(string $groupId): array
    {
        $cacheKey = "ranking:group:{$groupId}:global";
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        try {
            return Cache::lock("lock:{$cacheKey}", 10)
                ->block(5, function () use ($groupId, $cacheKey) {
                    if ($fresh = Cache::get($cacheKey)) return $fresh;

                    $total   = Ranking::where('group_id', $groupId)->count();
                    $entries = $this->fetchRows($groupId, 100);
                    $data    = [$entries, $total];

                    Cache::put($cacheKey, $data, 90);
                    return $data;
                });
        } catch (LockTimeoutException) {
            $total   = Ranking::where('group_id', $groupId)->count();
            $entries = $this->fetchRows($groupId, 100);
            return [$entries, $total];
        }
    }

    /** Executes the ranking query and shapes the array.
     *
     * Position follows standard competition ranking (1224 pattern):
     * players with identical (total_points, exact_scores) share the same rank;
     * the next distinct group starts at its 1-based ordinal index.
     * e.g. 6pts/2ex, 6pts/2ex, 3pts/1ex, 1pt/0ex → positions 1, 1, 3, 4.
     */
    private function fetchRows(string $groupId, ?int $limit): array
    {
        $rows = Ranking::where('rankings.group_id', $groupId)
            ->join('users', 'users.id', '=', 'rankings.user_id')
            ->select('rankings.*')
            ->with('user:id,name,avatar_url')
            ->orderByDesc('rankings.total_points')
            ->orderByDesc('rankings.exact_scores')
            ->orderByRaw('LOWER(users.name)')
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->get();

        $result     = [];
        $rank       = 0;
        $idx        = 0;
        $prevPoints = null;
        $prevExact  = null;

        foreach ($rows as $r) {
            $idx++;
            if ($r->total_points !== $prevPoints || $r->exact_scores !== $prevExact) {
                $rank       = $idx;
                $prevPoints = $r->total_points;
                $prevExact  = $r->exact_scores;
            }
            $result[] = [
                'position'          => $rank,
                'total_points'      => $r->total_points,
                'exact_scores'      => $r->exact_scores,
                'correct_results'   => $r->correct_results,
                'total_predictions' => $r->total_predictions,
                'user'              => [
                    'id'         => $r->user->id,
                    'name'       => $r->user->name,
                    'avatar_url' => $r->user->avatar_url,
                ],
            ];
        }

        return $result;
    }
}

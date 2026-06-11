<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Http\Resources\PredictionResource;
use App\Models\FootballMatch;
use App\Models\GroupMember;
use App\Models\Prediction;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status   = (string) $request->query('status', '');
        $stage    = (string) $request->query('stage', '');
        $cacheKey = "matches:list:{$status}:{$stage}";

        // Fast path: serve pre-rendered array from cache — Eloquent models are
        // never stored in Redis to avoid Carbon/serialization failures on read.
        $data = Cache::tags(['matches'])->get($cacheKey);

        if ($data === null) {
            try {
                $data = Cache::lock("lock:{$cacheKey}", 10)
                    ->block(5, function () use ($status, $stage, $cacheKey) {
                        if ($fresh = Cache::tags(['matches'])->get($cacheKey)) {
                            return $fresh;
                        }

                        $data = $this->queryAndRender($status, $stage);
                        Cache::tags(['matches'])->put($cacheKey, $data, $this->listCacheTtl());
                        return $data;
                    });
            } catch (LockTimeoutException) {
                $data = $this->queryAndRender($status, $stage);
            }
        }

        return response()->json(['data' => $data]);
    }

    private function queryAndRender(string $status, string $stage): array
    {
        $query = FootballMatch::query();
        if ($status !== '') $query->where('status', $status);
        if ($stage  !== '') $query->where('stage',  $stage);

        $matches = $query
            ->orderByRaw("CASE status
                WHEN 'LIVE'      THEN 1
                WHEN 'SCHEDULED' THEN 2
                WHEN 'FINISHED'  THEN 3
                WHEN 'POSTPONED' THEN 4
                WHEN 'CANCELLED' THEN 5
                ELSE 6
            END")
            ->orderBy('starts_at')
            ->get();

        return MatchResource::collection($matches)->resolve();
    }

    /** Shorter TTL while games are live or awaiting final scores. */
    private function listCacheTtl(): int
    {
        $hasLive = FootballMatch::where('status', 'LIVE')->exists();

        $hasPendingScores = FootballMatch::query()
            ->where('status', 'FINISHED')
            ->where(fn ($q) => $q->whereNull('home_score')->orWhereNull('away_score'))
            ->where('starts_at', '>=', now()->subHours(48))
            ->exists();

        return ($hasLive || $hasPendingScores) ? 10 : 30;
    }

    public function show(FootballMatch $match, Request $request): JsonResponse
    {
        $user = $request->user();

        // Subquery: all user_ids that share at least one group with the authenticated user.
        // Kept as a DB subquery to avoid materialising potentially large arrays in PHP.
        $peerSubquery = GroupMember::whereIn(
            'group_id',
            $user->groupMembers()->select('group_id')
        )->select('user_id');

        // Predictions for this match from every peer (includes the user themselves)
        $groupPredictions = Prediction::where('match_id', $match->id)
            ->whereIn('user_id', $peerSubquery)
            ->with('user:id,name,avatar_url')
            ->get();

        $myPrediction = $groupPredictions->firstWhere('user_id', $user->id);

        return response()->json([
            'data'              => (new MatchResource($match))->resolve(),
            'user_prediction'   => $myPrediction
                ? (new PredictionResource($myPrediction))->resolve()
                : null,
            'group_predictions' => PredictionResource::collection($groupPredictions)->resolve(),
        ]);
    }
}

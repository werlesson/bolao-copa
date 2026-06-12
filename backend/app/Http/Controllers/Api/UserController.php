<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePushSubscriptionRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\Prediction;
use App\Models\Ranking;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    private const COOKIE_NAME = 'auth_token';

    private const PHASE_ORDER = [
        'GROUP_STAGE',
        'ROUND_OF_16',
        'QUARTER_FINALS',
        'SEMI_FINALS',
        'FINAL',
    ];

    private const PHASE_SHORT_LABELS = [
        'GROUP_STAGE'    => 'Grupos',
        'ROUND_OF_16'    => 'Oitavas',
        'QUARTER_FINALS' => 'Quartas',
        'SEMI_FINALS'    => 'Semi-final',
        'FINAL'          => 'Final',
    ];

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function stats(Request $request): JsonResponse
    {
        $user     = $request->user();
        $globalId = $this->globalGroupId();

        $totalPredictions = Prediction::where('user_id', $user->id)->count();

        $ranking = Ranking::where('group_id', $globalId)
            ->where('user_id', $user->id)
            ->first();

        $correctResults = $ranking?->correct_results ?? 0;
        $exactScores    = $ranking?->exact_scores ?? 0;
        $totalPoints    = $ranking?->total_points ?? 0;
        $position       = $ranking ? $this->computeGlobalPosition($globalId, $user->id) : null;

        $finishedMatchesCount = FootballMatch::where('status', MatchStatus::FINISHED->value)->count();
        $maxPossiblePoints    = $finishedMatchesCount * ScoringService::MAX_POINTS_PER_MATCH;

        $accuracyPercent = $maxPossiblePoints > 0
            ? (int) round(($totalPoints / $maxPossiblePoints) * 100)
            : null;

        $predictions = Prediction::query()
            ->with('match:id,stage,status')
            ->where('user_id', $user->id)
            ->get();

        return response()->json([
            'position'          => $position,
            'total_points'      => $totalPoints,
            'total_predictions' => $totalPredictions,
            'exact_scores'      => $exactScores,
            'correct_results'   => $correctResults,
            'accuracy_percent'  => $accuracyPercent,
            'phase_points'      => $this->buildPhasePoints($predictions),
        ]);
    }

    public function update(UpdateUserRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user());
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        $request->user()->update(['onboarding_done' => true]);

        return response()->json(['message' => 'Onboarding concluído.']);
    }

    public function storePushSubscription(UpdatePushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->update([
            'push_subscription' => $request->validated(),
        ]);

        return response()->json(['message' => 'Subscription salva.'], 201);
    }

    public function destroyPushSubscription(Request $request): JsonResponse
    {
        $request->user()->update(['push_subscription' => null]);

        return response()->json(['message' => 'Subscription removida.']);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'deactivated_at'    => now(),
            'push_subscription' => null,
        ]);

        $user->tokens()->delete();

        return $this->clearAuthCookie(
            response()->json(['message' => 'Conta desativada com sucesso.'])
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return $this->clearAuthCookie(
            response()->json(['message' => 'Conta excluída permanentemente.'])
        );
    }

    private function clearAuthCookie(JsonResponse $response): JsonResponse
    {
        $expired = Cookie::forget(
            self::COOKIE_NAME,
            '/',
            config('session.domain'),
        );

        return $response->withCookie($expired);
    }

    private function globalGroupId(): string
    {
        return Cache::rememberForever('group:global:id', fn () =>
            Group::where('is_global', true)->firstOrFail()->id
        );
    }

    private function computeGlobalPosition(string $groupId, string $userId): ?int
    {
        $rankings = Ranking::where('rankings.group_id', $groupId)
            ->join('users', 'users.id', '=', 'rankings.user_id')
            ->whereNull('users.deactivated_at')
            ->orderByDesc('rankings.total_points')
            ->orderByDesc('rankings.exact_scores')
            ->orderByRaw('LOWER(users.name)')
            ->select('rankings.user_id', 'rankings.total_points', 'rankings.exact_scores')
            ->get();

        $rank       = 0;
        $idx        = 0;
        $prevPoints = null;
        $prevExact  = null;

        foreach ($rankings as $row) {
            $idx++;
            if ($row->total_points !== $prevPoints || $row->exact_scores !== $prevExact) {
                $rank       = $idx;
                $prevPoints = $row->total_points;
                $prevExact  = $row->exact_scores;
            }
            if ($row->user_id === $userId) {
                return $rank;
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Prediction>  $predictions
     * @return array<int, array{stage: string, label: string, points: int}>
     */
    private function buildPhasePoints($predictions): array
    {
        $byStage = [];

        foreach ($predictions as $prediction) {
            if ($prediction->match->status !== MatchStatus::FINISHED->value) {
                continue;
            }
            if ($prediction->points_earned === null) {
                continue;
            }

            $stage = $prediction->match->stage;
            $byStage[$stage] = ($byStage[$stage] ?? 0) + $prediction->points_earned;
        }

        $rows = [];

        foreach (self::PHASE_ORDER as $stage) {
            $points = $byStage[$stage] ?? null;
            if ($points !== null && $points > 0) {
                $rows[] = [
                    'stage'  => $stage,
                    'label'  => self::PHASE_SHORT_LABELS[$stage] ?? $stage,
                    'points' => $points,
                ];
            }
        }

        foreach ($byStage as $stage => $points) {
            if (in_array($stage, self::PHASE_ORDER, true) || $points <= 0) {
                continue;
            }
            $rows[] = [
                'stage'  => $stage,
                'label'  => self::PHASE_SHORT_LABELS[$stage] ?? $stage,
                'points' => $points,
            ];
        }

        return $rows;
    }
}

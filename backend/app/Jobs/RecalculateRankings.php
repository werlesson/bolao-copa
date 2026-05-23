<?php

namespace App\Jobs;

use App\Models\GroupMember;
use App\Models\Prediction;
use App\Models\Ranking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecalculateRankings implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $matchId) {}

    /**
     * Unique per match: prevents duplicate ranking recalculations when the
     * same match triggers multiple dispatch calls (e.g. sync runs while a job
     * is already queued).
     */
    public function uniqueId(): string
    {
        return $this->matchId;
    }

    public function handle(): void
    {
        // Find every group that has at least one member who predicted this match.
        $groupIds = GroupMember::whereIn(
            'user_id',
            Prediction::where('match_id', $this->matchId)->select('user_id')
        )->distinct()->pluck('group_id');

        foreach ($groupIds as $groupId) {
            $this->recalculateGroup($groupId);

            // Invalidate both cache variants (group ranking + global top-100 if applicable).
            Cache::forget("ranking:group:{$groupId}");
            Cache::forget("ranking:group:{$groupId}:global");
        }
    }

    private function recalculateGroup(string $groupId): void
    {
        // Per-group lock prevents two concurrent RecalculateRankings jobs (from
        // different matches finishing simultaneously) from racing on updateOrCreate.
        Cache::lock("lock:ranking:recalc:{$groupId}", 60)
            ->block(30, function () use ($groupId) {
                $memberUserIds = GroupMember::where('group_id', $groupId)->pluck('user_id');

                // One query per group: aggregate all finished-match predictions for every member.
                $stats = Prediction::query()
                    ->join('matches', 'matches.id', '=', 'predictions.match_id')
                    ->where('matches.status', 'FINISHED')
                    ->whereIn('predictions.user_id', $memberUserIds)
                    ->groupBy('predictions.user_id')
                    ->select([
                        'predictions.user_id',
                        DB::raw('COALESCE(SUM(predictions.points_earned), 0)::int          AS total_points'),
                        DB::raw('COUNT(*) FILTER (WHERE predictions.points_earned = 3)::int AS exact_scores'),
                        DB::raw('COUNT(*) FILTER (WHERE predictions.points_earned >= 1)::int AS correct_results'),
                        DB::raw('COUNT(*) FILTER (WHERE predictions.points_earned IS NOT NULL)::int AS total_predictions'),
                    ])
                    ->get()
                    ->keyBy('user_id');

                foreach ($memberUserIds as $userId) {
                    $row = $stats->get($userId);

                    Ranking::updateOrCreate(
                        ['user_id' => $userId, 'group_id' => $groupId],
                        [
                            'total_points'      => $row ? (int) $row->total_points      : 0,
                            'exact_scores'      => $row ? (int) $row->exact_scores      : 0,
                            'correct_results'   => $row ? (int) $row->correct_results   : 0,
                            'total_predictions' => $row ? (int) $row->total_predictions : 0,
                        ]
                    );
                }
            });
    }
}

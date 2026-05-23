<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\FootballDataService;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncMatchResults implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(FootballDataService $football, ScoringService $scoring): void
    {
        $matches = $football->fetchMatches();

        foreach ($matches as $data) {
            $this->syncMatch($data, $scoring);
        }

        // Invalidate all match list caches so the next polling request reflects the new data.
        Cache::tags(['matches'])->flush();
    }

    private function syncMatch(array $data, ScoringService $scoring): void
    {
        $externalId = $data['id'];
        $apiStatus  = $data['status'];
        $newStatus  = $this->mapStatus($apiStatus);

        [$homeScore, $awayScore] = $this->extractScore($data['score'] ?? []);

        // Snapshot old status before the upsert
        $oldStatus = FootballMatch::where('external_id', $externalId)->value('status');

        $match = FootballMatch::updateOrCreate(
            ['external_id' => $externalId],
            [
                'home_team'  => $this->teamName($data['homeTeam'] ?? []),
                'away_team'  => $this->teamName($data['awayTeam'] ?? []),
                'home_flag'  => $this->teamCrest($data['homeTeam'] ?? []),
                'away_flag'  => $this->teamCrest($data['awayTeam'] ?? []),
                'starts_at'  => $data['utcDate'],
                'stage'      => $data['stage'] ?? 'REGULAR_SEASON',
                'group_name' => $this->groupLabel($data),
                'status'     => $newStatus,
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'synced_at'  => now(),
            ]
        );

        if ($oldStatus !== 'FINISHED' && $newStatus === 'FINISHED') {
            $this->handleFinished($match, $scoring);
        } elseif ($oldStatus !== $newStatus && in_array($newStatus, ['POSTPONED', 'CANCELLED'])) {
            $this->handleCancelledOrPostponed($match);
        }
    }

    /** Maps football-data.org statuses to app statuses (LIVE, SCHEDULED, …). */
    private function mapStatus(string $apiStatus): string
    {
        return match ($apiStatus) {
            'IN_PLAY', 'PAUSED' => 'LIVE',
            'SCHEDULED', 'TIMED' => 'SCHEDULED',
            'FINISHED' => 'FINISHED',
            'POSTPONED' => 'POSTPONED',
            'CANCELLED', 'AWARDED', 'SUSPENDED' => 'CANCELLED',
            default => 'SCHEDULED',
        };
    }

    private function groupLabel(array $data): ?string
    {
        $group = $data['group'] ?? null;

        // Tournament group stage (e.g. Copa do Mundo): prefer the group letter.
        // football-data.org returns "GROUP_A", "GROUP_B", etc. — strip the prefix.
        if ($group !== null && $group !== '') {
            $letter = (string) preg_replace('/^GROUP_/i', '', $group);
            return $letter !== '' ? $letter : (string) $group;
        }

        // League / regular season: use the matchday number.
        if (isset($data['matchday'])) {
            return (string) $data['matchday'];
        }

        return null;
    }

    private function teamCrest(array $team): ?string
    {
        return $team['crest'] ?? null;
    }

    private function handleFinished(FootballMatch $match, ScoringService $scoring): void
    {
        $predictions = Prediction::where('match_id', $match->id)->get();

        foreach ($predictions as $prediction) {
            $points = $scoring->calculate(
                $prediction->home_score,
                $prediction->away_score,
                $match->home_score,
                $match->away_score,
            );
            $prediction->update(['points_earned' => $points]);
        }

        RecalculateRankings::dispatch($match->id);
        SendMatchNotification::dispatch($match->id);
    }

    private function handleCancelledOrPostponed(FootballMatch $match): void
    {
        Prediction::where('match_id', $match->id)->update(['points_earned' => null]);
        RecalculateRankings::dispatch($match->id);
    }

    /**
     * Returns [homeScore, awayScore] using regularTime for knockout rounds
     * with extra time/penalties, and fullTime otherwise.
     *
     * The football-data.org API only populates regularTime when the match
     * went beyond 90 min (EXTRA_TIME or PENALTY_SHOOTOUT duration). For
     * regular-time results, fullTime already equals the 90-min score.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function extractScore(array $score): array
    {
        // regularTime is present and populated only for knockout matches that
        // went to extra time or penalties — this is the 90-min score.
        $regular = $score['regularTime'] ?? null;
        if ($regular !== null && $regular['home'] !== null) {
            return [(int) $regular['home'], (int) $regular['away']];
        }

        // Group stage or knockout resolved in 90 min.
        $full = $score['fullTime'] ?? [];
        return [
            isset($full['home']) ? (int) $full['home'] : null,
            isset($full['away']) ? (int) $full['away'] : null,
        ];
    }

    /** Returns null for TBD/unknown teams so the frontend can show "Times a definir". */
    private function teamName(array $team): ?string
    {
        $name = $team['shortName'] ?? $team['name'] ?? null;

        return ($name === null || $name === 'TBD') ? null : $name;
    }
}

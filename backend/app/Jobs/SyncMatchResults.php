<?php

namespace App\Jobs;

use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\FootballDataService;
use App\Services\ScoringService;
use App\Support\MatchStage;
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

        $this->backfillMissingScores($football, $scoring);

        // Invalidate all match list caches so the next polling request reflects the new data.
        Cache::tags(['matches'])->flush();
    }

    private function syncMatch(array $data, ScoringService $scoring): void
    {
        $externalId = $data['id'];
        $apiStatus  = $data['status'];
        $newStatus  = $this->mapStatus($apiStatus);

        [$homeScore, $awayScore] = $this->extractScore($data['score'] ?? [], $apiStatus);

        $existing = FootballMatch::where('external_id', $externalId)->first();
        $oldStatus = $existing?->status;
        $oldHome   = $existing?->home_score;
        $oldAway   = $existing?->away_score;

        // API may mark FINISHED before fullTime is populated — keep last known scores.
        if ($homeScore === null && $oldHome !== null) {
            $homeScore = $oldHome;
        }
        if ($awayScore === null && $oldAway !== null) {
            $awayScore = $oldAway;
        }

        $match = FootballMatch::updateOrCreate(
            ['external_id' => $externalId],
            [
                'home_team'  => $this->teamName($data['homeTeam'] ?? []),
                'away_team'  => $this->teamName($data['awayTeam'] ?? []),
                'home_flag'  => $this->teamCrest($data['homeTeam'] ?? []),
                'away_flag'  => $this->teamCrest($data['awayTeam'] ?? []),
                'starts_at'  => $data['utcDate'],
                'stage'      => MatchStage::normalize($data['stage'] ?? null),
                'group_name' => $this->groupLabel($data),
                'status'     => $newStatus,
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'synced_at'  => now(),
            ]
        );

        $scoresAvailable = $homeScore !== null && $awayScore !== null;
        $scoresJustArrived = $scoresAvailable
            && ($oldHome === null || $oldAway === null)
            && $newStatus === MatchStatus::FINISHED->value;

        $scoresChanged = $scoresAvailable
            && ($oldHome !== $homeScore || $oldAway !== $awayScore)
            && $newStatus === MatchStatus::FINISHED->value;

        if ($oldStatus !== MatchStatus::FINISHED->value && $newStatus === MatchStatus::FINISHED->value) {
            $this->applyMatchResults($match, $scoring, notify: true);
        } elseif ($newStatus === MatchStatus::FINISHED->value && ($scoresJustArrived || $scoresChanged)) {
            $this->applyMatchResults($match, $scoring, notify: $scoresJustArrived);
        } elseif ($oldStatus !== $newStatus && in_array($newStatus, [MatchStatus::POSTPONED->value, MatchStatus::CANCELLED->value])) {
            $this->handleCancelledOrPostponed($match);
        }
    }

    /** Re-fetch individual matches when bulk sync has FINISHED but no score yet. */
    private function backfillMissingScores(FootballDataService $football, ScoringService $scoring): void
    {
        $stale = FootballMatch::query()
            ->where('status', MatchStatus::FINISHED->value)
            ->whereNotNull('external_id')
            ->where(function ($q) {
                $q->whereNull('home_score')->orWhereNull('away_score');
            })
            ->where('starts_at', '>=', now()->subHours(48))
            ->limit(10)
            ->get();

        foreach ($stale as $match) {
            try {
                $data = $football->fetchMatch((int) $match->external_id);
            } catch (\Throwable) {
                continue;
            }

            $this->syncMatch($data, $scoring);
        }
    }

    /** Maps football-data.org statuses to app statuses (LIVE, SCHEDULED, …). */
    private function mapStatus(string $apiStatus): string
    {
        return match ($apiStatus) {
            'IN_PLAY', 'PAUSED'                 => MatchStatus::LIVE->value,
            'SCHEDULED', 'TIMED'                => MatchStatus::SCHEDULED->value,
            'FINISHED'                          => MatchStatus::FINISHED->value,
            'POSTPONED'                         => MatchStatus::POSTPONED->value,
            'CANCELLED', 'AWARDED', 'SUSPENDED' => MatchStatus::CANCELLED->value,
            default                             => MatchStatus::SCHEDULED->value,
        };
    }

    private function groupLabel(array $data): ?string
    {
        $group = $data['group'] ?? null;

        if ($group !== null && $group !== '') {
            $letter = (string) preg_replace('/^GROUP_/i', '', $group);
            return $letter !== '' ? $letter : (string) $group;
        }

        if (isset($data['matchday'])) {
            return (string) $data['matchday'];
        }

        return null;
    }

    private function teamCrest(array $team): ?string
    {
        return $team['crest'] ?? null;
    }

    private function applyMatchResults(FootballMatch $match, ScoringService $scoring, bool $notify): void
    {
        if ($match->home_score === null || $match->away_score === null) {
            return;
        }

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

        if ($notify) {
            SendMatchNotification::dispatch($match->id);
        }
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
     * @return array{0: int|null, 1: int|null}
     */
    private function extractScore(array $score, string $apiStatus = 'FINISHED'): array
    {
        $regular = $score['regularTime'] ?? null;
        if ($regular !== null && $regular['home'] !== null) {
            return [(int) $regular['home'], (int) $regular['away']];
        }

        $full = $score['fullTime'] ?? [];
        if (isset($full['home'], $full['away'])) {
            return [(int) $full['home'], (int) $full['away']];
        }

        // During live play the current tally is sometimes only in halfTime briefly.
        if (in_array($apiStatus, ['IN_PLAY', 'PAUSED'], true)) {
            $half = $score['halfTime'] ?? [];
            if (isset($half['home'], $half['away'])) {
                return [(int) $half['home'], (int) $half['away']];
            }
        }

        return [null, null];
    }

    /** Returns null for TBD/unknown teams so the frontend can show "Times a definir". */
    private function teamName(array $team): ?string
    {
        $name = $team['shortName'] ?? $team['name'] ?? null;

        return ($name === null || $name === 'TBD') ? null : $name;
    }
}

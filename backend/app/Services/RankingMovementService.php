<?php

namespace App\Services;

use App\DTO\MovementContext;
use App\Enums\MatchStatus;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Prediction;
use App\Models\Ranking;
use App\Models\User;

class RankingMovementService
{
    public function __construct(
        private readonly RankingPositionService $positionService,
    ) {}

    /**
     * @param  array<string, int>  $positionsBefore
     * @param  array<string, int>  $positionsAfter
     */
    public function buildContext(
        Group $group,
        FootballMatch $match,
        array $positionsBefore,
        array $positionsAfter,
    ): ?MovementContext {
        $memberUserIds = GroupMember::where('group_id', $group->id)->pluck('user_id');

        $predictions = Prediction::query()
            ->where('match_id', $match->id)
            ->whereIn('user_id', $memberUserIds)
            ->get()
            ->keyBy('user_id');

        if ($predictions->isEmpty()) {
            return null;
        }

        $users = User::whereIn('id', $memberUserIds)
            ->whereNull('deactivated_at')
            ->pluck('name', 'id');

        $scoreLabel = $this->scoreLabel($match);
        $highlights = $this->buildHighlights(
            $predictions,
            $users,
            $positionsBefore,
            $positionsAfter,
        );

        $podiumAfter = $this->buildPodium($group->id, $positionsAfter, $users);
        $stats       = $this->buildStats($predictions, $positionsBefore, $positionsAfter, $memberUserIds->count());

        return new MovementContext(
            matchId: $match->id,
            homeTeam: $match->home_team,
            awayTeam: $match->away_team,
            scoreLabel: $scoreLabel,
            matchStatus: $match->status,
            groupId: $group->id,
            groupName: $group->name,
            memberCount: $memberUserIds->count(),
            highlights: $this->selectHighlights($highlights),
            podiumAfter: $podiumAfter,
            stats: $stats,
        );
    }

    public function isSignificant(MovementContext $ctx): bool
    {
        if ($ctx->isCancelledOrPostponed()) {
            return false;
        }

        foreach ($ctx->highlights as $highlight) {
            if (in_array($highlight['type'], [
                'new_leader',
                'lost_leadership',
                'entered_podium',
                'left_podium',
                'big_jump',
                'big_drop',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    public function toTemplate(MovementContext $ctx): string
    {
        $groupRef = $this->groupRef($ctx->groupName);

        if ($ctx->isCancelledOrPostponed()) {
            $reason = $ctx->matchStatus === MatchStatus::POSTPONED->value ? 'adiado' : 'cancelado';

            return "Jogo {$reason} — pontos voltaram atrás e o {$groupRef} recalculou o ranking.";
        }

        if ($ctx->highlights !== []) {
            return $this->templateFromHighlights($ctx, $groupRef);
        }

        if ($ctx->stats['moved'] === 0) {
            if ($ctx->stats['scored'] > 0) {
                return "Rolou gol, rolou ponto, mas no {$groupRef} ninguém trocou de lugar.";
            }

            return "Zerou o chute no {$groupRef} — ninguém pontuou.";
        }

        return "Uns pontinhos aqui e ali, mas o pódio tá igualzinho no {$groupRef}.";
    }

    /**
     * @param  array<int, array{type: string, user: string, from: int|null, to: int, pts: int, score: int}>  $highlights
     * @return array<int, array{type: string, user: string, from: int|null, to: int, pts: int}>
     */
    public function selectHighlights(array $highlights, int $max = 3): array
    {
        usort($highlights, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $selected = [];
        $usedUsers = [];

        foreach ($highlights as $highlight) {
            if (count($selected) >= $max) {
                break;
            }

            if (isset($usedUsers[$highlight['user']])) {
                continue;
            }

            unset($highlight['score']);
            $selected[] = $highlight;
            $usedUsers[$highlight['user']] = true;
        }

        // Force new leader into selection when present.
        foreach ($highlights as $highlight) {
            if ($highlight['type'] !== 'new_leader') {
                continue;
            }

            foreach ($selected as $item) {
                if ($item['type'] === 'new_leader') {
                    continue 2;
                }
            }

            if (count($selected) >= $max) {
                array_pop($selected);
            }

            unset($highlight['score']);
            array_unshift($selected, $highlight);
            break;
        }

        return array_values($selected);
    }

    private function templateFromHighlights(MovementContext $ctx, string $groupRef): string
    {
        $parts = [];

        foreach ($ctx->highlights as $highlight) {
            $name = $highlight['user'];

            $parts[] = match ($highlight['type']) {
                'new_leader'       => "{$name} passou na frente e agora manda no {$groupRef}",
                'lost_leadership'  => "{$name} perdeu a ponta no {$groupRef}",
                'entered_podium'   => "{$name} invadiu o pódio no {$groupRef}",
                'left_podium'      => "{$name} saiu voando do pódio no {$groupRef}",
                'big_jump'         => "{$name} deu um pulo do {$highlight['from']}º pro {$highlight['to']}º",
                'big_drop'         => "{$name} despencou do {$highlight['from']}º pro {$highlight['to']}º",
                'exact_score'      => "{$name} cravou o placar (+3)",
                default            => "{$name} pontuou no {$groupRef}",
            };
        }

        $lead = implode('. ', array_slice($parts, 0, 2));
        $tail = '';

        if ($ctx->stats['moved'] >= 5 || ($ctx->memberCount > 10 && $ctx->stats['scored'] >= 5)) {
            $tail = " {$ctx->stats['scored']} pontuaram e {$ctx->stats['moved']} trocaram de lugar — mexeu geral.";
        }

        return rtrim("{$lead}.{$tail}", '.');
    }

    private function scoreLabel(FootballMatch $match): string
    {
        if ($match->home_score === null || $match->away_score === null) {
            return '×';
        }

        return "{$match->home_score}×{$match->away_score}";
    }

    private function groupRef(string $groupName): string
    {
        return $groupName;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Prediction>  $predictions
     * @param  \Illuminate\Support\Collection<string, string>  $users
     * @param  array<string, int>  $positionsBefore
     * @param  array<string, int>  $positionsAfter
     * @return array<int, array{type: string, user: string, from: int|null, to: int, pts: int, score: int}>
     */
    private function buildHighlights(
        $predictions,
        $users,
        array $positionsBefore,
        array $positionsAfter,
    ): array {
        $highlights = [];

        foreach ($predictions as $userId => $prediction) {
            $name = $users->get($userId);
            if ($name === null) {
                continue;
            }

            $from = $positionsBefore[$userId] ?? null;
            $to   = $positionsAfter[$userId] ?? null;
            $pts  = (int) ($prediction->points_earned ?? 0);

            if ($to === null) {
                continue;
            }

            if ($from !== null && $from !== $to) {
                $delta = abs($from - $to);

                if ($from === 1 && $to !== 1) {
                    $highlights[] = $this->highlight('lost_leadership', $name, $from, $to, $pts, 90);
                } elseif ($to === 1 && $from !== 1) {
                    $highlights[] = $this->highlight('new_leader', $name, $from, $to, $pts, 100);
                } elseif ($from > 3 && $to <= 3) {
                    $highlights[] = $this->highlight('entered_podium', $name, $from, $to, $pts, 80);
                } elseif ($from <= 3 && $to > 3) {
                    $highlights[] = $this->highlight('left_podium', $name, $from, $to, $pts, 75);
                } elseif ($from !== null && $to < $from && $delta >= 3) {
                    $highlights[] = $this->highlight('big_jump', $name, $from, $to, $pts, 50 + $delta);
                } elseif ($from !== null && $to > $from && $delta >= 3) {
                    $highlights[] = $this->highlight('big_drop', $name, $from, $to, $pts, 45 + $delta);
                }
            }

            if ($pts === 3 && ! $this->hasHighlightType($highlights, $name, ['new_leader', 'big_jump', 'entered_podium'])) {
                $highlights[] = $this->highlight('exact_score', $name, $from, $to, $pts, 40);
            }
        }

        return $highlights;
    }

    /**
     * @param  array<int, array{type: string, user: string, from: int|null, to: int, pts: int, score: int}>  $highlights
     * @param  list<string>  $types
     */
    private function hasHighlightType(array $highlights, string $user, array $types): bool
    {
        foreach ($highlights as $highlight) {
            if ($highlight['user'] === $user && in_array($highlight['type'], $types, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{type: string, user: string, from: int|null, to: int, pts: int, score: int} */
    private function highlight(string $type, string $user, ?int $from, int $to, int $pts, int $score): array
    {
        return compact('type', 'user', 'from', 'to', 'pts', 'score');
    }

    /**
     * @param  array<string, int>  $positionsAfter
     * @param  \Illuminate\Support\Collection<string, string>  $users
     * @return array<int, array{user: string, pos: int, pts: int}>
     */
    private function buildPodium(string $groupId, array $positionsAfter, $users): array
    {
        $rankings = Ranking::query()
            ->where('group_id', $groupId)
            ->whereIn('user_id', $users->keys())
            ->get()
            ->keyBy('user_id');

        $entries = [];

        foreach ($positionsAfter as $userId => $position) {
            if ($position > 3) {
                continue;
            }

            $name = $users->get($userId);
            if ($name === null) {
                continue;
            }

            $entries[] = [
                'user' => $name,
                'pos'  => $position,
                'pts'  => (int) ($rankings->get($userId)?->total_points ?? 0),
            ];
        }

        usort($entries, fn (array $a, array $b) => $a['pos'] <=> $b['pos']);

        return $entries;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Prediction>  $predictions
     * @param  array<string, int>  $positionsBefore
     * @param  array<string, int>  $positionsAfter
     * @return array{scored: int, moved: int, exact_hits: int}
     */
    private function buildStats($predictions, array $positionsBefore, array $positionsAfter, int $memberCount): array
    {
        $scored     = 0;
        $exactHits  = 0;
        $moved      = 0;

        foreach ($predictions as $userId => $prediction) {
            $pts = (int) ($prediction->points_earned ?? 0);
            if ($pts > 0) {
                $scored++;
            }
            if ($pts === 3) {
                $exactHits++;
            }

            $from = $positionsBefore[$userId] ?? null;
            $to   = $positionsAfter[$userId] ?? null;
            if ($from !== null && $to !== null && $from !== $to) {
                $moved++;
            }
        }

        if ($memberCount <= 10 && $moved <= 5) {
            return ['scored' => $scored, 'moved' => $moved, 'exact_hits' => $exactHits];
        }

        return ['scored' => $scored, 'moved' => $moved, 'exact_hits' => $exactHits];
    }
}

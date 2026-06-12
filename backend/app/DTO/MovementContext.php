<?php

namespace App\DTO;

class MovementContext
{
    /**
     * @param  array<int, array{type: string, user: string, from: int|null, to: int, pts: int}>  $highlights
     * @param  array<int, array{user: string, pos: int, pts: int}>  $podiumAfter
     * @param  array{scored: int, moved: int, exact_hits: int}  $stats
     */
    public function __construct(
        public readonly string $matchId,
        public readonly string $homeTeam,
        public readonly string $awayTeam,
        public readonly string $scoreLabel,
        public readonly string $matchStatus,
        public readonly string $groupId,
        public readonly string $groupName,
        public readonly int $memberCount,
        public readonly array $highlights,
        public readonly array $podiumAfter,
        public readonly array $stats,
    ) {}

    /** @return list<string> */
    public function allowedNames(): array
    {
        $names = [];

        foreach ($this->highlights as $highlight) {
            $names[] = $highlight['user'];
        }

        foreach ($this->podiumAfter as $entry) {
            $names[] = $entry['user'];
        }

        return array_values(array_unique($names));
    }

    public function isCancelledOrPostponed(): bool
    {
        return in_array($this->matchStatus, ['POSTPONED', 'CANCELLED'], true);
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'match' => [
                'home'   => $this->homeTeam,
                'away'   => $this->awayTeam,
                'score'  => $this->scoreLabel,
                'status' => $this->matchStatus,
            ],
            'group' => [
                'name'    => $this->groupName,
                'members' => $this->memberCount,
            ],
            'podium_after' => $this->podiumAfter,
            'highlights'   => $this->highlights,
            'stats'        => $this->stats,
        ];
    }

    /** @return array<string, mixed> */
    public function toSummary(): array
    {
        return $this->toPayload();
    }
}

<?php

namespace App\Services;

use App\Models\Ranking;
use Illuminate\Support\Collection;

class RankingPositionService
{
    /**
     * Competition ranking (1224): same (points, exatos) share position.
     *
     * @param  list<string>  $groupIds
     * @return array<string, int> group_id => position
     */
    public function userPositionsByGroup(string $userId, array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        $rows = Ranking::query()
            ->whereIn('rankings.group_id', $groupIds)
            ->join('group_members', function ($join) {
                $join->on('group_members.user_id', '=', 'rankings.user_id')
                    ->on('group_members.group_id', '=', 'rankings.group_id');
            })
            ->join('users', 'users.id', '=', 'rankings.user_id')
            ->whereNull('users.deactivated_at')
            ->select('rankings.*')
            ->orderBy('rankings.group_id')
            ->orderByDesc('rankings.total_points')
            ->orderByDesc('rankings.exact_scores')
            ->orderByRaw('LOWER(users.name)')
            ->get()
            ->groupBy('group_id');

        $positions = [];

        foreach ($groupIds as $groupId) {
            $position = $this->positionInGroup($rows->get($groupId, collect()), $userId);
            if ($position !== null) {
                $positions[$groupId] = $position;
            }
        }

        return $positions;
    }

    private function positionInGroup(Collection $rows, string $userId): ?int
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $rank       = 0;
        $idx        = 0;
        $prevPoints = null;
        $prevExact  = null;
        $userRank   = null;

        foreach ($rows as $row) {
            $idx++;
            if ($row->total_points !== $prevPoints || $row->exact_scores !== $prevExact) {
                $rank       = $idx;
                $prevPoints = $row->total_points;
                $prevExact  = $row->exact_scores;
            }
            if ($row->user_id === $userId) {
                $userRank = $rank;
                break;
            }
        }

        return $userRank;
    }
}

<?php

namespace Database\Seeders;

use App\Enums\MatchStatus;
use App\Jobs\RecalculateRankings;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Prediction;
use App\Models\Ranking;
use App\Models\RankingBulletin;
use App\Models\User;
use App\Services\RankingPositionService;
use App\Services\ScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RankingBulletinDemoSeeder extends Seeder
{
    private const MAIN_EMAIL = 'werlessono@gmail.com';

    public function run(): void
    {
        $scoring = app(ScoringService::class);

        $finished = FootballMatch::query()
            ->where('status', MatchStatus::FINISHED->value)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->orderBy('starts_at')
            ->get();

        if ($finished->count() < 2) {
            $this->command?->error('Precisa de pelo menos 2 jogos finalizados com placar.');

            return;
        }

        /** @var FootballMatch $latest */
        $latest  = $finished->last();
        $buildup = $finished->slice(0, -1)->take(-4)->values();
        $groups  = Group::all();

        $this->command?->info("Jogo do bulletin: {$latest->home_team} {$latest->home_score}×{$latest->away_score} {$latest->away_team}");

        DB::transaction(function () use ($groups, $buildup, $scoring) {
            RankingBulletin::query()->delete();

            foreach ($buildup as $match) {
                foreach ($groups as $group) {
                    $memberIds = GroupMember::where('group_id', $group->id)->pluck('user_id')->all();
                    if ($memberIds !== []) {
                        $this->seedRandomPredictions($memberIds, $match, $scoring);
                    }
                }
            }
        });

        foreach ($buildup as $match) {
            RecalculateRankings::dispatchSync($match->id);
        }

        $position = app(RankingPositionService::class);

        DB::transaction(function () use ($groups, $latest, $scoring, $position) {
            foreach ($groups as $group) {
                $memberIds = GroupMember::where('group_id', $group->id)->pluck('user_id')->all();
                if ($memberIds === []) {
                    continue;
                }

                $positionsBefore = $position->positionsForGroup($group->id);
                $this->seedDramaticPredictions($memberIds, $latest, $scoring, $positionsBefore);
            }
        });

        RecalculateRankings::dispatchSync($latest->id);

        // Horizon processa GenerateRankingBulletin de forma assíncrona.
        sleep(3);

        $count = RankingBulletin::count();

        $this->command?->info("{$count} bulletins criados em {$groups->count()} grupos.");

        $main = User::where('email', self::MAIN_EMAIL)->first();
        if ($main) {
            $sample = RankingBulletin::query()
                ->whereIn('group_id', GroupMember::where('user_id', $main->id)->pluck('group_id'))
                ->with('group', 'match')
                ->latest('created_at')
                ->first();

            if ($sample) {
                $this->command?->line("Exemplo ({$sample->group->name}): {$sample->content}");
            }
        }
    }

    /**
     * @param  list<string>  $memberIds
     */
    private function seedRandomPredictions(array $memberIds, FootballMatch $match, ScoringService $scoring): void
    {
        $realHome = (int) $match->home_score;
        $realAway = (int) $match->away_score;

        foreach ($memberIds as $userId) {
            if (Prediction::where('user_id', $userId)->where('match_id', $match->id)->exists()) {
                continue;
            }

            $predHome = random_int(0, 3);
            $predAway = random_int(0, 3);

            Prediction::create([
                'user_id'       => $userId,
                'match_id'      => $match->id,
                'home_score'    => $predHome,
                'away_score'    => $predAway,
                'points_earned' => $scoring->calculate($predHome, $predAway, $realHome, $realAway),
            ]);
        }
    }

    /**
     * @param  list<string>  $memberIds
     * @param  array<string, int>  $positionsBefore
     */
    private function seedDramaticPredictions(
        array $memberIds,
        FootballMatch $match,
        ScoringService $scoring,
        array $positionsBefore,
    ): void {
        $realHome = (int) $match->home_score;
        $realAway = (int) $match->away_score;

        $byPosition = [];
        foreach ($positionsBefore as $userId => $pos) {
            $byPosition[$pos] = $userId;
        }

        $exact = ['home_score' => $realHome, 'away_score' => $realAway];
        $wrong = $this->wrongPrediction($realHome, $realAway);

        /** @var array<string, array{home_score: int, away_score: int}> $planned */
        $planned = [];

        if (isset($byPosition[1], $byPosition[2])) {
            $planned[$byPosition[2]] = $exact;
            $planned[$byPosition[1]] = $wrong;
        }

        foreach ([4, 5] as $pos) {
            if (isset($byPosition[$pos]) && ! isset($planned[$byPosition[$pos]])) {
                $planned[$byPosition[$pos]] = $exact;
                break;
            }
        }

        $main = User::where('email', self::MAIN_EMAIL)->first();
        if ($main && in_array($main->id, $memberIds, true) && ! isset($planned[$main->id])) {
            $planned[$main->id] = [
                'home_score' => $realHome,
                'away_score' => max(0, $realAway - 1),
            ];
        }

        foreach ($memberIds as $userId) {
            $pred = $planned[$userId] ?? [
                'home_score' => random_int(0, 3),
                'away_score' => random_int(0, 3),
            ];

            Prediction::updateOrCreate(
                ['user_id' => $userId, 'match_id' => $match->id],
                [
                    'home_score'    => $pred['home_score'],
                    'away_score'    => $pred['away_score'],
                    'points_earned' => $scoring->calculate(
                        $pred['home_score'],
                        $pred['away_score'],
                        $realHome,
                        $realAway,
                    ),
                ],
            );
        }
    }

    /** @return array{home_score: int, away_score: int} */
    private function wrongPrediction(int $realHome, int $realAway): array
    {
        if ($realHome > $realAway) {
            return ['home_score' => $realHome, 'away_score' => $realHome];
        }

        if ($realAway > $realHome) {
            return ['home_score' => $realAway, 'away_score' => $realHome];
        }

        return ['home_score' => $realHome + 1, 'away_score' => $realAway];
    }
}

<?php

namespace App\Jobs;

use App\Contracts\RankingBulletinGenerator;
use App\Exceptions\BulletinGenerationException;
use App\Models\FootballMatch;
use App\Models\Group;
use App\Models\Prediction;
use App\Models\RankingBulletin;
use App\DTO\MovementContext;
use App\Services\BulletinContentValidator;
use App\Services\RankingMovementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateRankingBulletin implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, int>  $positionsBefore
     * @param  array<string, int>  $positionsAfter
     */
    public function __construct(
        public readonly string $groupId,
        public readonly string $matchId,
        public readonly array $positionsBefore,
        public readonly array $positionsAfter,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->matchId}:{$this->groupId}";
    }

    public function handle(
        RankingMovementService $movementService,
        RankingBulletinGenerator $generator,
        BulletinContentValidator $validator,
    ): void {
        $group = Group::find($this->groupId);
        $match = FootballMatch::find($this->matchId);

        if (! $group || ! $match) {
            return;
        }

        $hasPredictions = Prediction::where('match_id', $this->matchId)
            ->whereIn('user_id', function ($query) {
                $query->select('user_id')
                    ->from('group_members')
                    ->where('group_id', $this->groupId);
            })
            ->exists();

        if (! $hasPredictions) {
            return;
        }

        $context = $movementService->buildContext(
            $group,
            $match,
            $this->positionsBefore,
            $this->positionsAfter,
        );

        if ($context === null) {
            return;
        }

        $content = $movementService->toTemplate($context);
        $source  = 'template';

        if ($this->shouldUseAi($movementService, $context)) {
            try {
                $aiText = $generator->generate($context->toPayload());

                if ($validator->isValid($aiText, $context)) {
                    $content = $aiText;
                    $source  = 'ai';
                    $this->incrementDailyBudget();
                } else {
                    Log::info('bulletin.ai_rejected', [
                        'group_id' => $this->groupId,
                        'match_id' => $this->matchId,
                        'preview'  => mb_substr($aiText, 0, 120),
                    ]);
                }
            } catch (BulletinGenerationException $e) {
                Log::warning('bulletin.ai_failed', [
                    'group_id' => $this->groupId,
                    'match_id' => $this->matchId,
                    'reason'   => $e->getMessage(),
                ]);
            }
        }

        RankingBulletin::updateOrCreate(
            [
                'group_id' => $this->groupId,
                'match_id' => $this->matchId,
            ],
            [
                'content'          => $content,
                'source'           => $source,
                'movement_summary' => $context->toSummary(),
                'created_at'       => now(),
            ]
        );

        Cache::forget("ranking:group:{$this->groupId}:bulletins:1");
        Cache::forget("ranking:group:{$this->groupId}:bulletins:3");
    }

    private function shouldUseAi(RankingMovementService $movementService, MovementContext $context): bool
    {
        if (! config('services.ai_ranking.enabled')) {
            return false;
        }

        if (! config('services.gemini.api_key')) {
            return false;
        }

        if (! $movementService->isSignificant($context)) {
            return false;
        }

        return $this->dailyBudgetAllows();
    }

    private function dailyBudgetAllows(): bool
    {
        $budget = (int) config('services.ai_ranking.daily_budget', 0);

        if ($budget <= 0) {
            return true;
        }

        $key   = 'ai_ranking:budget:'.now()->format('Y-m-d');
        $count = (int) Cache::get($key, 0);

        return $count < $budget;
    }

    private function incrementDailyBudget(): void
    {
        $budget = (int) config('services.ai_ranking.daily_budget', 0);

        if ($budget <= 0) {
            return;
        }

        $key = 'ai_ranking:budget:'.now()->format('Y-m-d');
        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);
    }
}

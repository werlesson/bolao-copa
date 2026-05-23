<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUpcomingMatchReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PushNotificationService $push): void
    {
        // Matches kicking off in ~1 hour (55–65 min window)
        $matches = FootballMatch::whereBetween('starts_at', [
            now()->addMinutes(55),
            now()->addMinutes(65),
        ])->where('status', 'SCHEDULED')->get();

        foreach ($matches as $match) {
            $userIdsWithPrediction = Prediction::where('match_id', $match->id)
                ->pluck('user_id');

            $users = User::whereNotNull('push_subscription')
                ->whereNotIn('id', $userIdsWithPrediction)
                ->get();

            if ($users->isEmpty()) {
                continue;
            }

            $label = "{$match->home_team} × {$match->away_team}";

            $push->sendToUsers(
                $users->all(),
                'Jogo em 1 hora!',
                "⏰ {$label} começa em 1h — faça seu palpite!"
            );
        }
    }
}

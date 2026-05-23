<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMatchNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $matchId) {}

    public function handle(PushNotificationService $push): void
    {
        $match = FootballMatch::find($this->matchId);
        if (!$match) {
            return;
        }

        $predictions = $match->predictions()
            ->with('user')
            ->whereNotNull('points_earned')
            ->get();

        $matchLabel = "{$match->home_team} {$match->home_score}×{$match->away_score} {$match->away_team}";

        $users = [];
        $messages = [];

        foreach ($predictions as $prediction) {
            $user = $prediction->user;
            if (empty($user?->push_subscription)) {
                continue;
            }

            $pts = $prediction->points_earned;

            if ($pts === 3) {
                $body = "{$matchLabel} — você acertou o placar exato! +3 pts 🎯";
            } elseif ($pts === 1) {
                $body = "{$matchLabel} — você acertou o resultado! +1 pt 👍";
            } else {
                $body = "{$matchLabel} — 0 pts dessa vez 😔";
            }

            $users[]             = $user;
            $messages[$user->id] = $body;
        }

        // Send individually so each user gets their personalized message
        foreach ($users as $user) {
            $push->sendToUsers([$user], 'Resultado do jogo!', $messages[$user->id]);
        }
    }
}

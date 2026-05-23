<?php

namespace App\Services;

use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct()
    {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('services.vapid.subject'),
                'publicKey'  => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);
    }

    /**
     * Send a push notification to multiple users.
     * Automatically cleans up expired subscriptions.
     *
     * @param User[] $users
     */
    public function sendToUsers(array $users, string $title, string $body): void
    {
        // Map endpoint → userId for expired-subscription cleanup
        $endpointToUserId = [];

        foreach ($users as $user) {
            $sub = $user->push_subscription;
            if (empty($sub['endpoint'])) {
                continue;
            }

            $subscription = Subscription::create([
                'endpoint'  => $sub['endpoint'],
                'publicKey' => $sub['keys']['p256dh'] ?? null,
                'authToken' => $sub['keys']['auth'] ?? null,
            ]);

            $payload = json_encode(['title' => $title, 'body' => $body]);

            $this->webPush->queueNotification($subscription, $payload);

            $endpointToUserId[$sub['endpoint']] = $user->id;
        }

        foreach ($this->webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $userId = $endpointToUserId[$report->getEndpoint()] ?? null;
                if ($userId) {
                    User::where('id', $userId)->update(['push_subscription' => null]);
                }
            }
        }
    }
}

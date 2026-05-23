<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class WebpushVapidCommand extends Command
{
    protected $signature = 'webpush:vapid {--show : Display the keys instead of modifying .env}';

    protected $description = 'Generate VAPID keys for web push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        if ($this->option('show')) {
            $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
            $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

            return self::SUCCESS;
        }

        $path = $this->laravel->environmentFilePath();
        $contents = file_get_contents($path);

        $updated = preg_replace(
            '/^VAPID_PUBLIC_KEY=.*/m',
            'VAPID_PUBLIC_KEY='.$keys['publicKey'],
            $contents,
            count: $publicCount
        );

        $updated = preg_replace(
            '/^VAPID_PRIVATE_KEY=.*/m',
            'VAPID_PRIVATE_KEY='.$keys['privateKey'],
            $updated,
            count: $privateCount
        );

        if ($publicCount === 0 || $privateCount === 0) {
            $this->components->error('VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY must exist in .env.');

            return self::FAILURE;
        }

        if (filled(env('VAPID_PUBLIC_KEY')) || filled(env('VAPID_PRIVATE_KEY'))) {
            $this->components->warn('Existing VAPID keys were overwritten. Do not regenerate in production — it invalidates all push subscriptions.');
        }

        file_put_contents($path, $updated);

        $this->components->info('VAPID keys set successfully.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate VAPID keys for browser push notifications';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (\Throwable $e) {
            $this->error('OpenSSL could not generate EC keys on this machine.');
            $this->line('Run this instead, then paste into .env:');
            $this->newLine();
            $this->line('npx web-push generate-vapid-keys');
            $this->newLine();
            $this->comment('Map Public Key -> VAPID_PUBLIC_KEY and Private Key -> VAPID_PRIVATE_KEY');

            return self::FAILURE;
        }

        $this->info('Add these to your .env file:');
        $this->newLine();
        $this->line('VAPID_SUBJECT="mailto:'.(config('aura.contact.email') ?: 'admin@example.com').'"');
        $this->line('VAPID_PUBLIC_KEY="'.$keys['publicKey'].'"');
        $this->line('VAPID_PRIVATE_KEY="'.$keys['privateKey'].'"');
        $this->newLine();
        $this->comment('Then run: php artisan config:clear');

        return self::SUCCESS;
    }
}

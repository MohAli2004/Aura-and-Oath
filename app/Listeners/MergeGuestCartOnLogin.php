<?php

namespace App\Listeners;

use App\Services\CartService;
use Illuminate\Auth\Events\Login;

class MergeGuestCartOnLogin
{
    public function __construct(protected CartService $cartService) {}

    public function handle(Login $event): void
    {
        $this->cartService->mergeGuestCartIntoUser($event->user);

        $event->user->forceFill(['last_login_at' => now()])->save();
    }
}

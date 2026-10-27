<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireDeliveryConfirmationsCommand extends Command
{
    protected $signature = 'orders:expire-delivery-confirmations';

    protected $description = 'Carry on as normal with orders whose delivery-charge confirmation window has run out';

    public function handle(OrderService $orders): int
    {
        $count = $orders->expireDeliveryConfirmations();
        $this->info("Auto-confirmed {$count} order(s).");

        return self::SUCCESS;
    }
}

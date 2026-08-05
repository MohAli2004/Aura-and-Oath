<?php

namespace App\Http\Middleware;

use App\Support\InventoryLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInventoryUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! InventoryLock::isUnlocked()) {
            return redirect()
                ->route('admin.inventory.index')
                ->with('error', 'Inventory editing is locked. Enter your admin password to unlock.');
        }

        InventoryLock::touch();

        return $next($request);
    }
}

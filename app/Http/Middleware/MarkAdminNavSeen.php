<?php

namespace App\Http\Middleware;

use App\Services\AdminNavBadgeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MarkAdminNavSeen
{
    public function __construct(protected AdminNavBadgeService $badges) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isAdmin() && $request->isMethod('GET')) {
            $section = $this->badges->sectionForRoute($request->route()?->getName());
            if ($section) {
                $this->badges->markSeen($user, $section);
            }
        }

        return $next($request);
    }
}

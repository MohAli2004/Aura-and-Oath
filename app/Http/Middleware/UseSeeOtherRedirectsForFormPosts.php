<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After form mutations, use 303 See Other so the browser history stores a GET
 * instead of the POST/PUT/PATCH/DELETE. Browser Back then returns to the prior
 * page instead of replaying the form submission.
 */
class UseSeeOtherRedirectsForFormPosts
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $response instanceof RedirectResponse
            && $response->getStatusCode() === Response::HTTP_FOUND
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        ) {
            $response->setStatusCode(Response::HTTP_SEE_OTHER);
        }

        return $response;
    }
}

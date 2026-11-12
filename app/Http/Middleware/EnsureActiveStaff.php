<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveStaff
{
    public function handle(Request $request, Closure $next)
    {
        $request->user()?->refresh();
        abort_unless($request->user()?->active && in_array($request->user()->role, ['admin', 'worker'], true), 403);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    /**
     * Handle an incoming request.
     * Only allows users with ADMIN role.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->attributes->get('user');

        if ($user && $user->role === 'ADMIN') {
            return $next($request);
        }

        return response()->json(['error' => 'Akses ditolak. Membutuhkan hak akses ADMIN.'], 403);
    }
}

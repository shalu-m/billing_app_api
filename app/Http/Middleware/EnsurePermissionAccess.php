<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissionAccess
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user || !collect($permissions)->contains(fn ($permission) => $user->hasPermission($permission))) {
            return response()->json([
                'message' => 'You do not have permission to access this page.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\AppAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopEnabled
{
    public function handle(Request $request, Closure $next, string $shop): Response
    {
        if (!AppAccess::isShopEnabled($shop)) {
            return response()->json([
                'message' => 'This shop is not enabled.',
            ], 403);
        }

        return $next($request);
    }
}

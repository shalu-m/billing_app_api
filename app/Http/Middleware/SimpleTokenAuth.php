<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SimpleTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (!$plainToken) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $apiToken = ApiToken::with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (!$apiToken || !$apiToken->user || !$apiToken->user->is_active) {
            return response()->json([
                'message' => 'Invalid token.',
            ], 401);
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        $request->setUserResolver(fn () => $apiToken->user);
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}

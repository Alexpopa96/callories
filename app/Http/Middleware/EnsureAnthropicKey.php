<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI features are billed to each user's own Anthropic key, so they stay closed until one is set.
 */
class EnsureAnthropicKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasAnthropicKey()) {
            return response()->json([
                'message' => 'Setează-ți cheia API Anthropic în Profil ca să folosești funcțiile AI.',
                'missing_api_key' => true,
            ], 403);
        }

        return $next($request);
    }
}

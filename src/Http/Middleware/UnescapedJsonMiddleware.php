<?php

namespace MacropaySolutions\CrufdWizard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MacropaySolutions\CrufdWizard\Providers\CrufdProvider;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * This will decode and encode again the response.
 * @see CrufdProvider::register() for JsonResponse as alternative
 */
class UnescapedJsonMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $response;
    }
}

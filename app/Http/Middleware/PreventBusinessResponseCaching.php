<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PreventBusinessResponseCaching
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->isBusinessRoute($request)) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    private function isBusinessRoute(Request $request): bool
    {
        return $request->is(
            'dashboard',
            'dashboard/*',
            'warehouse',
            'warehouse/*',
            'hod',
            'hod/*',
            'head-warehouse',
            'head-warehouse/*',
            'admin',
            'admin/*',
            'files/*',
        );
    }
}

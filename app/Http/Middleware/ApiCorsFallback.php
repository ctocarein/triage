<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiCorsFallback
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');
        $headers = $this->corsHeaders($origin);

        if ($request->isMethod('OPTIONS')) {
            return response()->noContent()->withHeaders($headers);
        }

        $response = $next($request);

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function corsHeaders(?string $origin): array
    {
        $allowedOrigins = array_values(array_filter(array_map(
            static fn ($value): string => rtrim(trim((string) $value), '/'),
            (array) config('cors.allowed_origins', [])
        )));

        $normalizedOrigin = rtrim(trim((string) $origin), '/');
        $isAllowed = $normalizedOrigin !== '' && in_array($normalizedOrigin, $allowedOrigins, true);

        $allowOrigin = $isAllowed ? $normalizedOrigin : ($allowedOrigins[0] ?? '');
        $allowMethods = implode(', ', (array) config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']));
        $allowHeaders = implode(', ', (array) config('cors.allowed_headers', ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With']));

        $headers = [
            'Vary' => 'Origin',
            'Access-Control-Allow-Methods' => $allowMethods,
            'Access-Control-Allow-Headers' => $allowHeaders,
        ];

        if ($allowOrigin !== '') {
            $headers['Access-Control-Allow-Origin'] = $allowOrigin;
        }

        if ((bool) config('cors.supports_credentials', false)) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }
}

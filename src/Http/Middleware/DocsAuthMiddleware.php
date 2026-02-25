<?php

namespace OpenSwag\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocsAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('openswag.docs_auth', []);

        if (! $this->isAuthEnabled($config)) {
            return $next($request);
        }

        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $apiKey = $config['api_key'] ?? '';
        $realm = $config['realm'] ?? 'API Documentation';

        // Try basic auth if username and password are configured
        if ($username !== '' && $password !== '') {
            return $this->handleBasicAuth($request, $next, $username, $password, $realm);
        }

        // Try API key auth if api_key is configured
        if ($apiKey !== '') {
            return $this->handleApiKeyAuth($request, $next, $apiKey, $realm);
        }

        // Auth enabled but no credentials configured — treat as disabled
        return $next($request);
    }

    private function isAuthEnabled(array $config): bool
    {
        if (empty($config['enabled'])) {
            return false;
        }

        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $apiKey = $config['api_key'] ?? '';

        // If enabled but all credentials are empty, treat as disabled
        return $username !== '' || $password !== '' || $apiKey !== '';
    }

    private function handleBasicAuth(Request $request, Closure $next, string $username, string $password, string $realm): Response
    {
        $providedUser = $request->getUser();
        $providedPass = $request->getPassword();

        if ($providedUser !== null && $providedPass !== null
            && hash_equals($username, $providedUser)
            && hash_equals($password, $providedPass)) {
            return $next($request);
        }

        return new Response('Unauthorized', 401, [
            'WWW-Authenticate' => sprintf('Basic realm="%s"', $realm),
        ]);
    }

    private function handleApiKeyAuth(Request $request, Closure $next, string $apiKey, string $realm): Response
    {
        $providedKey = $request->query('key', '');

        if (is_string($providedKey) && $providedKey !== '' && hash_equals($apiKey, $providedKey)) {
            return $next($request);
        }

        return new Response('Unauthorized', 401, [
            'WWW-Authenticate' => sprintf('Basic realm="%s"', $realm),
        ]);
    }
}

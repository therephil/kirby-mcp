<?php

declare(strict_types=1);

use Bnomei\KirbyMcp\Mcp\KirbyMcpRoute;
use Bnomei\KirbyMcp\Project\KirbyMcpConfig;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * @return array<string, mixed>
 */
function kirbyMcpRouteDecodeJson(string $body): array
{
    $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    expect($decoded)->toBeArray();

    return $decoded;
}

function kirbyMcpRouteInitializePayload(): string
{
    return json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
            'capabilities' => new stdClass(),
            'clientInfo' => [
                'name' => 'tests',
                'version' => 'dev',
            ],
        ],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}

/**
 * @param array<string, string> $env
 */
function kirbyMcpRouteWithHttpEnv(array $env, Closure $callback): mixed
{
    $names = [
        'KIRBY_MCP_HTTP_ENABLED',
        'KIRBY_MCP_HTTP_HOST',
        'KIRBY_MCP_HTTP_PORT',
        'KIRBY_MCP_HTTP_PATH',
        'KIRBY_MCP_HTTP_ALLOWED_ORIGINS',
        'KIRBY_MCP_HTTP_AUTH_MODE',
        'KIRBY_MCP_HTTP_TOKEN',
        'KIRBY_MCP_HTTP_OAUTH_ISSUER',
        'KIRBY_MCP_HTTP_OAUTH_AUDIENCE',
        'KIRBY_MCP_HTTP_OAUTH_JWKS_URI',
        'KIRBY_MCP_HTTP_SCOPES',
    ];

    $previous = [];
    foreach ($names as $name) {
        $value = getenv($name);
        $previous[$name] = is_string($value) ? $value : false;
        putenv($name);
    }

    foreach ($env as $name => $value) {
        putenv($name . '=' . $value);
    }

    KirbyMcpConfig::clearCache();

    try {
        return $callback();
    } finally {
        foreach ($previous as $name => $value) {
            if ($value === false) {
                putenv($name);
                continue;
            }

            putenv($name . '=' . $value);
        }

        KirbyMcpConfig::clearCache();
    }
}

it('keeps the copied Kirby MCP route closed while HTTP is disabled', function (): void {
    kirbyMcpRouteWithHttpEnv([], function (): void {
        $factory = new HttpFactory();
        $request = $factory->createServerRequest('POST', 'http://127.0.0.1/mcp');

        $response = KirbyMcpRoute::handle(cmsPath(), $request);

        expect($response->code())->toBe(404);
        expect($response->body())->toContain('HTTP MCP route is disabled.');
    });
});

it('serves initialize through the opt-in Kirby route adapter', function (): void {
    kirbyMcpRouteWithHttpEnv([
        'KIRBY_MCP_HTTP_ENABLED' => '1',
        'KIRBY_MCP_HTTP_AUTH_MODE' => 'shared-token',
        'KIRBY_MCP_HTTP_TOKEN' => 'local-secret',
    ], function (): void {
        $factory = new HttpFactory();
        $request = $factory->createServerRequest('POST', 'http://127.0.0.1/mcp')
            ->withHeader('Authorization', 'Bearer local-secret')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream(kirbyMcpRouteInitializePayload()));

        $response = KirbyMcpRoute::handle(cmsPath(), $request);

        expect($response->code())->toBe(200);
        expect($response->headers()['Mcp-Session-Id'] ?? '')->not()->toBe('');

        $payload = kirbyMcpRouteDecodeJson($response->body());
        expect($payload['result']['serverInfo']['name'] ?? null)->toBe('Kirby MCP');
    });
});

it('fails closed for shared-token Kirby route requests on public hosts', function (): void {
    kirbyMcpRouteWithHttpEnv([
        'KIRBY_MCP_HTTP_ENABLED' => '1',
        'KIRBY_MCP_HTTP_AUTH_MODE' => 'shared-token',
        'KIRBY_MCP_HTTP_TOKEN' => 'local-secret',
    ], function (): void {
        $factory = new HttpFactory();
        $request = $factory->createServerRequest('POST', 'https://example.test/mcp')
            ->withHeader('Authorization', 'Bearer local-secret')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream(kirbyMcpRouteInitializePayload()));

        $response = KirbyMcpRoute::handle(cmsPath(), $request);

        expect($response->code())->toBe(503);
        expect($response->body())->toContain('HTTP shared-token auth is only allowed for loopback requests.');
    });
});

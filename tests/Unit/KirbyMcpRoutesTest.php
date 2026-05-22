<?php

declare(strict_types=1);

use Bnomei\KirbyMcp\Mcp\KirbyMcpRoutes;

it('builds the copied Kirby route bundle for HTTP MCP', function (): void {
    $routes = KirbyMcpRoutes::routes();

    expect($routes)->toHaveCount(2)
        ->and($routes[0]['pattern'])->toBe('mcp')
        ->and($routes[0]['method'])->toBe('GET|POST|DELETE|OPTIONS')
        ->and($routes[0]['name'])->toBe('kirby-mcp.mcp')
        ->and($routes[0]['action'])->toBeInstanceOf(Closure::class)
        ->and($routes[1]['pattern'])->toBe('.well-known/oauth-protected-resource')
        ->and($routes[1]['method'])->toBe('GET')
        ->and($routes[1]['name'])->toBe('kirby-mcp.oauth-protected-resource')
        ->and($routes[1]['action'])->toBeInstanceOf(Closure::class);
});

it('maps custom MCP URL paths to Kirby route patterns', function (): void {
    $routes = KirbyMcpRoutes::routes('/api/mcp?ignored=true');

    expect($routes[0]['pattern'])->toBe('api/mcp');
});

it('exposes focused route helpers', function (): void {
    expect(KirbyMcpRoutes::mcp('/custom')[0]['pattern'])->toBe('custom')
        ->and(KirbyMcpRoutes::oauth()[0]['pattern'])->toBe(KirbyMcpRoutes::oauthProtectedResourceMetadata()[0]['pattern']);
});

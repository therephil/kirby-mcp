<?php

declare(strict_types=1);

use Bnomei\KirbyMcp\Project\KirbyMcpConfig;
use Bnomei\KirbyMcp\Project\KirbyMcpHttpConfig;

function kirbyMcpHttpConfigTempRoot(array $config): string
{
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kirby-mcp-http-config-' . bin2hex(random_bytes(4));
    $configDir = $root . DIRECTORY_SEPARATOR . '.kirby-mcp';
    mkdir($configDir, 0777, true);
    file_put_contents(
        $configDir . DIRECTORY_SEPARATOR . 'mcp.json',
        json_encode($config, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    KirbyMcpConfig::clearCache();

    return $root;
}

function kirbyMcpHttpConfigRemoveRoot(string $root): void
{
    @unlink($root . DIRECTORY_SEPARATOR . '.kirby-mcp' . DIRECTORY_SEPARATOR . 'mcp.json');
    @rmdir($root . DIRECTORY_SEPARATOR . '.kirby-mcp');
    @rmdir($root);
    KirbyMcpConfig::clearCache();
}

function kirbyMcpHttpConfigWithEnv(array $env, Closure $callback): mixed
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

    $original = [];
    foreach ($names as $name) {
        $original[$name] = getenv($name);
        putenv($name);
    }

    foreach ($env as $name => $value) {
        putenv($name . '=' . $value);
    }

    try {
        KirbyMcpConfig::clearCache();
        return $callback();
    } finally {
        foreach ($original as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
        }
        KirbyMcpConfig::clearCache();
    }
}

it('keeps HTTP disabled by default with loopback /mcp defaults', function (): void {
    kirbyMcpHttpConfigWithEnv([], function (): void {
        $config = KirbyMcpConfig::load(sys_get_temp_dir() . '/missing-kirby-mcp-config')->http();

        expect($config->enabled)->toBeFalse()
            ->and($config->host)->toBe('127.0.0.1')
            ->and($config->port)->toBe(8765)
            ->and($config->path)->toBe('/mcp')
            ->and($config->validationErrors())->toBe([]);
    });
});

it('lets environment values override project HTTP config', function (): void {
    $root = kirbyMcpHttpConfigTempRoot([
        'http' => [
            'enabled' => false,
            'host' => '127.0.0.1',
            'port' => 9999,
            'path' => '/from-config',
            'allowedOrigins' => ['http://config.test'],
            'auth' => [
                'mode' => 'shared-token',
                'token' => 'config-token',
            ],
        ],
    ]);

    try {
        kirbyMcpHttpConfigWithEnv([
            'KIRBY_MCP_HTTP_ENABLED' => '1',
            'KIRBY_MCP_HTTP_HOST' => 'localhost',
            'KIRBY_MCP_HTTP_PORT' => '8766',
            'KIRBY_MCP_HTTP_PATH' => 'from-env',
            'KIRBY_MCP_HTTP_ALLOWED_ORIGINS' => 'http://127.0.0.1:3000,http://localhost:3000',
            'KIRBY_MCP_HTTP_AUTH_MODE' => 'shared-token',
            'KIRBY_MCP_HTTP_TOKEN' => 'env-token',
            'KIRBY_MCP_HTTP_SCOPES' => 'kirby-mcp:read,kirby-mcp:write',
        ], function () use ($root): void {
            $config = KirbyMcpConfig::load($root)->http();

            expect($config->enabled)->toBeTrue()
                ->and($config->host)->toBe('localhost')
                ->and($config->port)->toBe(8766)
                ->and($config->path)->toBe('/from-env')
                ->and($config->allowedOrigins)->toBe(['http://127.0.0.1:3000', 'http://localhost:3000'])
                ->and($config->authMode)->toBe(KirbyMcpHttpConfig::AUTH_MODE_SHARED_TOKEN)
                ->and($config->sharedToken)->toBe('env-token')
                ->and($config->scopes)->toBe(['kirby-mcp:read', 'kirby-mcp:write'])
                ->and($config->validationErrors())->toBe([]);
        });
    } finally {
        kirbyMcpHttpConfigRemoveRoot($root);
    }
});

it('rejects enabled HTTP without auth material', function (): void {
    $root = kirbyMcpHttpConfigTempRoot([
        'http' => [
            'enabled' => true,
        ],
    ]);

    try {
        kirbyMcpHttpConfigWithEnv([], function () use ($root): void {
            expect(KirbyMcpConfig::load($root)->http()->validationErrors())
                ->toContain('HTTP auth is required when HTTP is enabled.');
        });
    } finally {
        kirbyMcpHttpConfigRemoveRoot($root);
    }
});

it('rejects shared-token HTTP on non-loopback binds', function (): void {
    $root = kirbyMcpHttpConfigTempRoot([
        'http' => [
            'enabled' => true,
            'host' => '0.0.0.0',
            'auth' => [
                'mode' => 'shared-token',
                'token' => 'local-secret',
            ],
        ],
    ]);

    try {
        kirbyMcpHttpConfigWithEnv([], function () use ($root): void {
            expect(KirbyMcpConfig::load($root)->http()->validationErrors())
                ->toContain('HTTP shared-token auth is only allowed for loopback hosts.')
                ->toContain('Non-loopback HTTP binds require OAuth auth.');
        });
    } finally {
        kirbyMcpHttpConfigRemoveRoot($root);
    }
});

it('rejects wildcard allowed origins', function (): void {
    $root = kirbyMcpHttpConfigTempRoot([
        'http' => [
            'enabled' => true,
            'allowedOrigins' => ['*'],
            'auth' => [
                'mode' => 'shared-token',
                'token' => 'local-secret',
            ],
        ],
    ]);

    try {
        kirbyMcpHttpConfigWithEnv([], function () use ($root): void {
            expect(KirbyMcpConfig::load($root)->http()->validationErrors())
                ->toContain('HTTP allowed origins must not contain wildcard *.');
        });
    } finally {
        kirbyMcpHttpConfigRemoveRoot($root);
    }
});

it('accepts OAuth config for non-loopback HTTP binds', function (): void {
    $root = kirbyMcpHttpConfigTempRoot([
        'http' => [
            'enabled' => true,
            'host' => '0.0.0.0',
            'auth' => [
                'mode' => 'oauth',
                'issuer' => 'https://auth.example.test',
                'audience' => 'https://example.test/mcp',
                'jwksUri' => 'https://auth.example.test/.well-known/jwks.json',
            ],
        ],
    ]);

    try {
        kirbyMcpHttpConfigWithEnv([], function () use ($root): void {
            expect(KirbyMcpConfig::load($root)->http()->validationErrors())->toBe([]);
        });
    } finally {
        kirbyMcpHttpConfigRemoveRoot($root);
    }
});

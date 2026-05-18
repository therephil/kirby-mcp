<?php

declare(strict_types=1);

namespace Bnomei\KirbyMcp\Mcp\Http;

final class HttpAuthScopes
{
    public const READ = 'kirby-mcp:read';
    public const RUNTIME = 'kirby-mcp:runtime';
    public const WRITE = 'kirby-mcp:write';
    public const EXECUTE = 'kirby-mcp:execute';
    public const ADMIN = 'kirby-mcp:admin';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::READ,
            self::RUNTIME,
            self::WRITE,
            self::EXECUTE,
            self::ADMIN,
        ];
    }
}

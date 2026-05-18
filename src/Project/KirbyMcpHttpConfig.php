<?php

declare(strict_types=1);

namespace Bnomei\KirbyMcp\Project;

final readonly class KirbyMcpHttpConfig
{
    public const DEFAULT_ENABLED = false;
    public const DEFAULT_HOST = '127.0.0.1';
    public const DEFAULT_PORT = 8765;
    public const DEFAULT_PATH = '/mcp';
    public const AUTH_MODE_OAUTH = 'oauth';
    public const AUTH_MODE_SHARED_TOKEN = 'shared-token';

    /**
     * @param array<int, string> $allowedOrigins
     * @param array<int, string> $scopes
     */
    public function __construct(
        public bool $enabled = self::DEFAULT_ENABLED,
        public string $host = self::DEFAULT_HOST,
        public int $port = self::DEFAULT_PORT,
        public string $path = self::DEFAULT_PATH,
        public array $allowedOrigins = [],
        public ?string $authMode = null,
        public ?string $sharedToken = null,
        public ?string $oauthIssuer = null,
        public ?string $oauthAudience = null,
        public ?string $oauthJwksUri = null,
        public array $scopes = [],
    ) {
    }

    public function isLoopbackHost(): bool
    {
        $host = strtolower(trim($this->host));

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_starts_with($host, '127.');
    }

    /**
     * @return array<int, string>
     */
    public function validationErrors(): array
    {
        if ($this->enabled === false) {
            return [];
        }

        $errors = [];

        if ($this->path === '' || !str_starts_with($this->path, '/')) {
            $errors[] = 'HTTP endpoint path must start with /.';
        }

        if ($this->port < 1 || $this->port > 65535) {
            $errors[] = 'HTTP port must be between 1 and 65535.';
        }

        foreach ($this->allowedOrigins as $origin) {
            if (trim($origin) === '*') {
                $errors[] = 'HTTP allowed origins must not contain wildcard *.';
            }
        }

        if ($this->authMode === null) {
            $errors[] = 'HTTP auth is required when HTTP is enabled.';
        } elseif (!in_array($this->authMode, [self::AUTH_MODE_OAUTH, self::AUTH_MODE_SHARED_TOKEN], true)) {
            $errors[] = 'HTTP auth mode must be oauth or shared-token.';
        }

        if ($this->authMode === self::AUTH_MODE_SHARED_TOKEN) {
            if ($this->sharedToken === null || $this->sharedToken === '') {
                $errors[] = 'HTTP shared-token auth requires KIRBY_MCP_HTTP_TOKEN or http.auth.token.';
            }

            if (!$this->isLoopbackHost()) {
                $errors[] = 'HTTP shared-token auth is only allowed for loopback hosts.';
            }
        }

        if ($this->authMode === self::AUTH_MODE_OAUTH) {
            if ($this->oauthIssuer === null || $this->oauthIssuer === '') {
                $errors[] = 'HTTP OAuth auth requires an issuer.';
            }

            if ($this->oauthAudience === null || $this->oauthAudience === '') {
                $errors[] = 'HTTP OAuth auth requires an audience.';
            }

            if ($this->oauthJwksUri === null || $this->oauthJwksUri === '') {
                $errors[] = 'HTTP OAuth auth requires a JWKS URI.';
            }
        }

        if (!$this->isLoopbackHost() && $this->authMode !== self::AUTH_MODE_OAUTH) {
            $errors[] = 'Non-loopback HTTP binds require OAuth auth.';
        }

        return array_values(array_unique($errors));
    }

    public function isValid(): bool
    {
        return $this->validationErrors() === [];
    }
}

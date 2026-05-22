<?php

declare(strict_types=1);

namespace Bnomei\KirbyMcp\Mcp\Http;

use Bnomei\KirbyMcp\Project\KirbyMcpHttpConfig;
use Bnomei\KirbyMcp\Project\KirbyMcpHttpToken;
use Mcp\Server\Transport\Http\OAuth\AuthorizationTokenValidatorInterface;
use Mcp\Server\Transport\Http\OAuth\JwksProvider;
use Mcp\Server\Transport\Http\OAuth\JwtTokenValidator;
use Mcp\Server\Transport\Http\OAuth\OidcDiscovery;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadata;

final readonly class HttpAuthFactory
{
    public function metadata(string $issuer, string $resource): ProtectedResourceMetadata
    {
        return new ProtectedResourceMetadata(
            authorizationServers: [$issuer],
            scopesSupported: HttpAuthScopes::all(),
            resource: $resource,
            resourceName: 'Kirby MCP',
        );
    }

    public function sharedTokenValidator(string $sharedToken, array $scopes = []): AuthorizationTokenValidatorInterface
    {
        return new SharedTokenValidator($sharedToken, $this->normalizedScopes($scopes));
    }

    /**
     * @param list<KirbyMcpHttpToken> $tokens
     */
    public function remoteTokenValidator(array $tokens): AuthorizationTokenValidatorInterface
    {
        return new RemoteTokenValidator($tokens);
    }

    public function oauthValidator(KirbyMcpHttpConfig $config): AuthorizationTokenValidatorInterface
    {
        return new JwtTokenValidator(
            issuer: (string) $config->oauthIssuer,
            audience: (string) $config->oauthAudience,
            jwksProvider: new JwksProvider(new OidcDiscovery()),
            jwksUri: $config->oauthJwksUri,
        );
    }

    /**
     * @return list<string>
     */
    private function normalizedScopes(array $scopes): array
    {
        $normalized = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                continue;
            }

            $scope = trim($scope);
            if ($scope !== '') {
                $normalized[] = $scope;
            }
        }

        return array_values(array_unique($normalized));
    }
}

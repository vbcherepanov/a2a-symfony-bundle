<?php

declare(strict_types=1);

namespace A2A\Bundle\Security;

use A2A\Protocol\{ErrorCode, ProtocolException};
use A2A\Security\{Authenticator, CallContext};
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final readonly class AccessTokenAuthenticator implements Authenticator
{
    public function __construct(private AccessTokenHandlerInterface $handler)
    {
    }
    public function authenticate(array $headers, string $tenant, ?float $deadline): CallContext
    {
        $headers = array_change_key_case($headers);
        if (!preg_match('/^Bearer (.+)$/iD', $headers['authorization'] ?? '', $matches)) {
            throw new ProtocolException(ErrorCode::Unauthenticated, 'Bearer authentication required');
        }
        try {
            $badge = $this->handler->getUserBadgeFrom($matches[1]);
        } catch (AuthenticationException $error) {
            throw new ProtocolException(ErrorCode::Unauthenticated, 'Invalid credentials', $error);
        }
        return new CallContext($badge->getUserIdentifier(), $tenant, array_values(array_filter(array_map('trim', explode(',', $headers['a2a-extensions'] ?? '')))), $headers['a2a-version'] ?? '1.0', $deadline);
    }
}

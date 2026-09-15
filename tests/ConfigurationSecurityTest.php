<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests;

use A2A\Bundle\DependencyInjection\A2AExtension;
use A2A\Bundle\Security\AccessTokenAuthenticator;
use A2A\Protocol\{ErrorCode, ProtocolException};
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class ConfigurationSecurityTest extends TestCase
{
    public function testMissingAuthenticationFailsDuringConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Configure a2a.auth');
        (new A2AExtension())->load([['executor' => 'executor', 'card_file' => 'card.json', 'public_url' => 'https://agent.example']], new ContainerBuilder());
    }
    public function testGrpcRequiresPublicEndpoint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('public_url is required');
        (new A2AExtension())->load([['executor' => 'executor', 'card_file' => 'card.json', 'public_url' => 'https://agent.example', 'transports' => ['grpc' => ['enabled' => true]]]], new ContainerBuilder());
    }
    public function testAccessTokenHandlerSuppliesPrincipalAndRejectsBadTokens(): void
    {
        $handler = new class () implements AccessTokenHandlerInterface {
            public function getUserBadgeFrom(string $accessToken): UserBadge
            {
                if ($accessToken !== 'verified-token') {
                    throw new BadCredentialsException('Invalid');
                }
                return new UserBadge('verified-principal');
            }
        };
        $auth = new AccessTokenAuthenticator($handler);
        $context = $auth->authenticate(['Authorization' => 'Bearer verified-token', 'A2A-Version' => '1.0'], 'tenant', null);
        self::assertSame('verified-principal', $context->principal);
        self::assertSame('tenant', $context->tenant);
        try {
            $auth->authenticate(['Authorization' => 'Bearer forged-token'], 'tenant', null);
            self::fail('Unverified token accepted');
        } catch (ProtocolException $error) {
            self::assertSame(ErrorCode::Unauthenticated, $error->error);
        }
    }
}

<?php

namespace Chatsaas\LaravelWidget\Tests\Feature;

use Chatsaas\LaravelWidget\AssistantIdentity;
use Chatsaas\LaravelWidget\Tests\TestCase;
use Chatsaas\LaravelWidget\Tests\TestUser;

class AssistantIdentityTest extends TestCase
{
    public function test_it_mints_a_token_that_decodes_with_the_configured_secret_and_respects_ttl(): void
    {
        $user = TestUser::create(['name' => 'Ada']);

        $token = AssistantIdentity::tokenFor($user, 60);

        [$headerB64, $payloadB64, $sigB64] = explode('.', $token);

        $decode = fn ($b64) => json_decode(base64_decode(strtr($b64, '-_', '+/')), true);
        $payload = $decode($payloadB64);

        $this->assertSame((string) $user->id, $payload['sub']);
        $this->assertSame($payload['iat'] + 60, $payload['exp']);

        $expectedSig = rtrim(strtr(base64_encode(hash_hmac(
            'sha256',
            "{$headerB64}.{$payloadB64}",
            'test-identity-secret',
            true
        )), '+/', '-_'), '=');

        $this->assertSame($expectedSig, $sigB64);
    }
}

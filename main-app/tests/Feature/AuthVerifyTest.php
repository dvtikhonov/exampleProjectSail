<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;
use Tests\TestCase;

class AuthVerifyTest extends TestCase
{
    use DatabaseTransactions;

    //  php artisan test --filter=AuthVerifyTest

    public function test_verify_rejects_request_without_bearer_token(): void
    {
        $this->postJson('/api/auth/verify')
            ->assertUnauthorized()
            ->assertJson(['active' => false]);
    }

    public function test_verify_rejects_invalid_jwt(): void
    {
        $this->postJson('/api/auth/verify', [], [
            'Authorization' => 'Bearer not-a-jwt',
        ])
            ->assertUnauthorized()
            ->assertJson(['active' => false]);
    }

    public function test_verify_rejects_jwt_without_jti_claim(): void
    {
        $this->postJson('/api/auth/verify', [], [
            'Authorization' => 'Bearer '.$this->jwt(),
        ])
            ->assertUnauthorized()
            ->assertJson(['active' => false]);
    }

    public function test_verify_rejects_missing_token_record(): void
    {
        $this->postJson('/api/auth/verify', [], [
            'Authorization' => 'Bearer '.$this->jwt(['jti' => (string) Str::uuid()]),
        ])
            ->assertUnauthorized()
            ->assertJson(['active' => false]);
    }

    public function test_verify_rejects_revoked_token(): void
    {
        $tokenId = $this->createAccessToken(revoked: true);

        $this->postJson('/api/auth/verify', [], [
            'Authorization' => 'Bearer '.$this->jwt(['jti' => $tokenId]),
        ])
            ->assertUnauthorized()
            ->assertJson(['active' => false]);
    }

    public function test_verify_rejects_expired_token(): void
    {
        $tokenId = $this->createAccessToken(expiresAt: now()->subMinute());

        $this->postJson('/api/auth/verify', [], [
            'Authorization' => 'Bearer '.$this->jwt(['jti' => $tokenId]),
        ])
            ->assertUnauthorized()
            ->assertJson(['active' => false]);
    }

    public function test_verify_accepts_active_token(): void
    {
        $user = User::factory()->create();
        $tokenId = $this->createAccessToken(userId: $user->id);

        $this->postJson('/api/auth/verify', [], [
            'Authorization' => 'Bearer '.$this->jwt(['jti' => $tokenId]),
        ])
            ->assertOk()
            ->assertHeader('X-User-Id', (string) $user->id)
            ->assertJson(['user_id' => $user->id]);
    }

    private function createAccessToken(
        ?int $userId = null,
        bool $revoked = false,
        mixed $expiresAt = null,
    ): string {
        $clientId = (string) Str::uuid();

        DB::table('oauth_clients')->insert([
            'id' => $clientId,
            'owner_type' => null,
            'owner_id' => null,
            'name' => 'Test Client',
            'secret' => null,
            'provider' => null,
            'redirect_uris' => json_encode([]),
            'grant_types' => json_encode([]),
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenId = (string) Str::uuid();

        DB::table('oauth_access_tokens')->insert([
            'id' => $tokenId,
            'user_id' => $userId,
            'client_id' => $clientId,
            'name' => 'Test Token',
            'scopes' => json_encode([]),
            'revoked' => $revoked,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => $expiresAt ?? now()->addHour(),
        ]);

        return $tokenId;
    }

    private function jwt(array $claims = []): string
    {
        $builder = new Builder(new JoseEncoder, ChainedFormatter::default());

        if (array_key_exists('jti', $claims)) {
            $builder = $builder->identifiedBy($claims['jti']);
            unset($claims['jti']);
        }

        foreach ($claims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder
            ->getToken(new Sha256, InMemory::plainText(str_repeat('a', 32)))
            ->toString();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;
use Tests\TestCase;

class AuthenticateBroadcastingPassportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->configureBroadcastingEnvironment();

        parent::setUp();

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'app_id' => 'test-app-id',
                'options' => [
                    'cluster' => 'mt1',
                    'host' => 'api-mt1.pusher.com',
                    'port' => 443,
                    'scheme' => 'https',
                    'encrypted' => true,
                    'useTLS' => true,
                ],
                'client_options' => [],
            ],
        ]);

        $this->app->forgetInstance(\Illuminate\Contracts\Broadcasting\Factory::class);
    }

    protected function tearDown(): void
    {
        putenv('BROADCAST_CONNECTION=null');
        unset($_ENV['BROADCAST_CONNECTION'], $_SERVER['BROADCAST_CONNECTION']);

        parent::tearDown();
    }

    private function configureBroadcastingEnvironment(): void
    {
        putenv('BROADCAST_CONNECTION=pusher');
        putenv('PUSHER_APP_KEY=test-key');
        putenv('PUSHER_APP_SECRET=test-secret');
        putenv('PUSHER_APP_ID=test-app-id');

        $_ENV['BROADCAST_CONNECTION'] = 'pusher';
        $_ENV['PUSHER_APP_KEY'] = 'test-key';
        $_ENV['PUSHER_APP_SECRET'] = 'test-secret';
        $_ENV['PUSHER_APP_ID'] = 'test-app-id';

        $_SERVER['BROADCAST_CONNECTION'] = 'pusher';
        $_SERVER['PUSHER_APP_KEY'] = 'test-key';
        $_SERVER['PUSHER_APP_SECRET'] = 'test-secret';
        $_SERVER['PUSHER_APP_ID'] = 'test-app-id';
    }

    public function test_broadcasting_auth_rejects_request_without_passport_token(): void
    {
        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-report-jobs.stats',
            'socket_id' => '123.456',
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_broadcasting_auth_accepts_valid_session_passport_token(): void
    {
        $user = User::factory()->create();
        $tokenId = $this->createAccessToken(userId: $user->id);
        $jwt = $this->jwt(['jti' => $tokenId]);

        $response = $this
            ->withSession(['passport_token' => $jwt])
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-report-jobs.stats',
                'socket_id' => '123.456',
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['auth']);
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

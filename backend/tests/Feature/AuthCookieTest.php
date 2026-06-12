<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthCookieTest extends TestCase
{
    public function test_authenticate_from_cookie_allows_api_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $response = $this->call(
            'GET',
            '/api/user',
            [],
            ['auth_token' => $token],
            [],
            $this->transformHeadersToServerVars([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ]),
        );

        $response->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_missing_cookie_returns_unauthenticated(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_logout_clears_auth_cookie(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $response = $this->call(
            'POST',
            '/api/auth/logout',
            [],
            ['auth_token' => $token],
            [],
            $this->transformHeadersToServerVars([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ]),
        );

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_oauth_state_rejects_tampered_signature(): void
    {
        $nonce = Str::random(32);
        Cache::put("oauth_nonce:{$nonce}", true, now()->addMinutes(10));

        $payload = ['nonce' => $nonce, 'pit' => null];
        $json    = json_encode($payload, JSON_THROW_ON_ERROR);
        $state   = base64_encode(json_encode([
            'payload' => $payload,
            'sig'     => hash_hmac('sha256', $json, (string) config('app.key')),
        ], JSON_THROW_ON_ERROR));

        $tampered = base64_encode(json_encode([
            'payload' => ['nonce' => $nonce, 'pit' => 'stolen-token'],
            'sig'     => hash_hmac('sha256', $json, (string) config('app.key')),
        ], JSON_THROW_ON_ERROR));

        $frontend = rtrim(config('app.frontend_url'), '/');

        $this->get("/api/auth/google/callback?state={$state}&code=invalid")
            ->assertRedirect("{$frontend}/login?message=" . urlencode('Não foi possível concluir o login com Google.'));

        $this->get("/api/auth/google/callback?state={$tampered}&code=invalid")
            ->assertRedirect("{$frontend}/login?message=" . urlencode('Sessão OAuth inválida. Tente novamente.'));
    }

    public function test_oauth_state_rejects_reused_nonce(): void
    {
        $nonce = Str::random(32);
        Cache::put("oauth_nonce:{$nonce}", true, now()->addMinutes(10));

        $payload = ['nonce' => $nonce, 'pit' => null];
        $json    = json_encode($payload, JSON_THROW_ON_ERROR);
        $state   = base64_encode(json_encode([
            'payload' => $payload,
            'sig'     => hash_hmac('sha256', $json, (string) config('app.key')),
        ], JSON_THROW_ON_ERROR));

        Cache::pull("oauth_nonce:{$nonce}");

        $frontend = rtrim(config('app.frontend_url'), '/');

        $this->get("/api/auth/google/callback?state={$state}&code=invalid")
            ->assertRedirect("{$frontend}/login?message=" . urlencode('Sessão OAuth inválida. Tente novamente.'));
    }
}

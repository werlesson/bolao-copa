<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    private const COOKIE_NAME = 'auth_token';
    private const COOKIE_DAYS = 30;
    private const STATE_TTL_MINUTES = 10;

    public function redirect(Request $request): RedirectResponse
    {
        $nonce = Str::random(32);
        Cache::put($this->nonceCacheKey($nonce), true, now()->addMinutes(self::STATE_TTL_MINUTES));

        $state = $this->encodeState([
            'nonce' => $nonce,
            'pit'   => $request->query('pending_invite_token'),
        ]);

        return Socialite::driver('google')
            ->with(['state' => $state])
            ->stateless()
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $loginUrl    = "{$frontendUrl}/login";

        try {
            $state = $this->decodeState((string) $request->query('state', ''));
            if ($state === null) {
                return redirect("{$loginUrl}?message=" . urlencode('Sessão OAuth inválida. Tente novamente.'));
            }

            $pendingInviteToken = $state['pit'] ?? null;

            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('google_id', $googleUser->getId())->first()
                ?? User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'google_id'      => $googleUser->getId(),
                    'avatar_url'     => $googleUser->getAvatar(),
                    'deactivated_at' => null,
                ]);
            } else {
                $user = User::create([
                    'name'       => $googleUser->getName(),
                    'email'      => $googleUser->getEmail(),
                    'google_id'  => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                ]);
            }

            $plainToken = $user->createToken(
                'web',
                ['*'],
                now()->addDays(self::COOKIE_DAYS),
            )->plainTextToken;

            $cookie = cookie(
                name: self::COOKIE_NAME,
                value: $plainToken,
                minutes: 60 * 24 * self::COOKIE_DAYS,
                path: '/',
                domain: config('session.domain'),
                secure: app()->isProduction(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            );

            $redirect = $pendingInviteToken
                ? "{$frontendUrl}/join/{$pendingInviteToken}"
                : "{$frontendUrl}/jogos";

            return redirect($redirect)->withCookie($cookie);
        } catch (Throwable) {
            return redirect("{$loginUrl}?message=" . urlencode('Não foi possível concluir o login com Google.'));
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        $expired = Cookie::forget(
            self::COOKIE_NAME,
            '/',
            config('session.domain'),
        );

        return response()->json(['message' => 'Logout realizado com sucesso.'])
            ->withCookie($expired);
    }

    /** @param array{nonce: string, pit?: mixed} $payload */
    private function encodeState(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig  = hash_hmac('sha256', $json, (string) config('app.key'));

        return base64_encode(json_encode(['payload' => $payload, 'sig' => $sig], JSON_THROW_ON_ERROR));
    }

    /** @return array{nonce: string, pit?: string|null}|null */
    private function decodeState(string $state): ?array
    {
        $decoded = json_decode(base64_decode($state, true) ?: '', true);
        if (! is_array($decoded) || ! isset($decoded['payload'], $decoded['sig'])) {
            return null;
        }

        $payload = $decoded['payload'];
        if (! is_array($payload) || empty($payload['nonce'])) {
            return null;
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig  = hash_hmac('sha256', $json, (string) config('app.key'));
        if (! hash_equals($sig, (string) $decoded['sig'])) {
            return null;
        }

        if (! Cache::pull($this->nonceCacheKey((string) $payload['nonce']))) {
            return null;
        }

        return [
            'nonce' => (string) $payload['nonce'],
            'pit'   => isset($payload['pit']) && is_string($payload['pit']) && $payload['pit'] !== ''
                ? $payload['pit']
                : null,
        ];
    }

    private function nonceCacheKey(string $nonce): string
    {
        return "oauth_nonce:{$nonce}";
    }
}

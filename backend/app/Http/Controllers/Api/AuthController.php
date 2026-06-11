<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private const COOKIE_NAME = 'auth_token';
    private const COOKIE_DAYS = 30;

    public function redirect(Request $request): RedirectResponse
    {
        $state = base64_encode(json_encode([
            'nonce' => Str::random(16),
            'pit'   => $request->query('pending_invite_token'),
        ]));

        return Socialite::driver('google')
            ->with(['state' => $state])
            ->stateless()
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $state             = json_decode(base64_decode((string) $request->query('state', '')), true);
        $pendingInviteToken = $state['pit'] ?? null;

        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->update([
                'google_id'       => $googleUser->getId(),
                'avatar_url'      => $googleUser->getAvatar(),
                'deactivated_at'  => null,
            ]);
        } else {
            $user = User::create([
                'name'       => $googleUser->getName(),
                'email'      => $googleUser->getEmail(),
                'google_id'  => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
            ]);
        }

        $user->tokens()->where('name', 'web')->delete();
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

        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        $redirect = $pendingInviteToken
            ? "{$frontendUrl}/join/{$pendingInviteToken}"
            : "{$frontendUrl}/jogos";

        return redirect($redirect)->withCookie($cookie);
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
}

<?php

namespace Azuriom\Plugin\Vote\Middleware;

use Azuriom\Http\Controllers\Api\ServerController;
use Azuriom\Http\Controllers\Auth\RegisterController;
use Azuriom\Models\User;
use Azuriom\Plugin\Vote\Support\GuestAccounts;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ClaimGuestAccount
{
    private const REGISTRATION_ACTIONS = [
        RegisterController::class.'@register',
        ServerController::class.'@register',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $this->isRegistration($request)) {
            return $next($request);
        }

        $name = $request->input('name');
        $guest = GuestAccounts::findGuest($name);

        if ($guest === null) {
            return $next($request);
        }

        try {
            return Cache::lock(GuestAccounts::lockKey($name), 30)
                ->block(10, fn () => $this->claim($request, $next, $guest, $name));
        } catch (LockTimeoutException) {
            return $next($request);
        }
    }

    private function claim(Request $request, Closure $next, User $guest, string $name): Response
    {
        $original = $guest->only(['name', 'game_id']);

        $guest->forceFill(['name' => '#vote-guest-'.$guest->id, 'game_id' => null])->saveQuietly();

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $guest->forceFill($original)->saveQuietly();

            throw $e;
        }

        $user = User::where('name', $name)->whereKeyNot($guest->id)->first();

        if ($user === null) {
            $guest->forceFill($original)->saveQuietly();

            return $response;
        }

        GuestAccounts::claim($guest, $user);

        return $response;
    }

    private function isRegistration(Request $request): bool
    {
        return in_array($request->route()?->getActionName(), self::REGISTRATION_ACTIONS, true);
    }
}

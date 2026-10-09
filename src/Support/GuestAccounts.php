<?php

namespace Azuriom\Plugin\Vote\Support;

use Azuriom\Models\Role;
use Azuriom\Models\User;
use Azuriom\Plugin\Vote\Models\Guest;
use Azuriom\Rules\GameAuth;
use Azuriom\Rules\Username;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GuestAccounts
{
    public const MAX_PER_IP_PER_HOUR = 5;

    public static function enabled(): bool
    {
        return ! setting('vote.auth-required', false)
            && (bool) setting('vote.guest-accounts', true);
    }

    public static function validateName(mixed $name): ?string
    {
        $validator = Validator::make(['name' => $name], [
            'name' => ['required', 'string', 'max:25', new Username(), new GameAuth()],
        ]);

        return $validator->fails() ? $validator->errors()->first('name') : null;
    }

    public static function pending(string $name): User
    {
        return (new User())->forceFill(['name' => $name, 'money' => 0]);
    }

    public static function findOrCreate(string $name, ?string $ip): ?User
    {
        return Cache::lock(static::lockKey($name), 10)->block(5, function () use ($name, $ip) {
            $user = User::firstWhere('name', $name);

            if ($user !== null) {
                return $user;
            }

            $limiterKey = 'vote.guests.'.$ip;

            if (RateLimiter::tooManyAttempts($limiterKey, static::MAX_PER_IP_PER_HOUR)) {
                return null;
            }

            RateLimiter::hit($limiterKey, 3600);

            return DB::transaction(function () use ($name, $ip) {
                $user = User::forceCreate([
                    'name' => $name,
                    'email' => null,
                    'password' => Str::random(32),
                    'role_id' => Role::defaultRoleId(),
                    'game_id' => game()->getUserUniqueId($name),
                ]);

                Guest::create(['user_id' => $user->id, 'ip' => $ip]);

                return $user;
            });
        });
    }

    public static function findGuest(mixed $name): ?User
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        return User::whereIn('id', Guest::select('user_id'))->firstWhere('name', $name);
    }

    public static function isGuest(User $user): bool
    {
        return Guest::where('user_id', $user->id)->exists();
    }

    public static function lockKey(string $name): string
    {
        return 'vote.guests.'.Str::lower($name);
    }

    public static function claim(User $guest, User $user): void
    {
        DB::transaction(function () use ($guest, $user) {
            foreach (static::referencingTables() as $table) {
                try {
                    DB::transaction(fn () => DB::table($table)
                        ->where('user_id', $guest->id)
                        ->update(['user_id' => $user->id]));
                } catch (QueryException $e) {
                    report($e);
                }
            }

            if ($guest->money > 0) {
                $user->increment('money', $guest->money);
            }

            static::forceDelete($guest);
        });
    }

    public static function forceDelete(User $guest): void
    {
        try {
            DB::transaction(fn () => User::whereKey($guest->id)->delete());
        } catch (QueryException) {
            Guest::where('user_id', $guest->id)->delete();
            $guest->delete();
        }
    }

    public static function hasReferences(User $guest): bool
    {
        foreach (static::referencingTables() as $table) {
            if (DB::table($table)->where('user_id', $guest->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function referencingTables(): array
    {
        return once(function () {
            $prefix = DB::getTablePrefix();
            $guestsTable = (new Guest())->getTable();

            return collect(Schema::getTables(Schema::getCurrentSchemaName()))
                ->pluck('name')
                ->map(fn (string $table) => $prefix !== '' && str_starts_with($table, $prefix)
                    ? substr($table, strlen($prefix))
                    : $table)
                ->reject(fn (string $table) => $table === $guestsTable)
                ->filter(fn (string $table) => Schema::hasColumn($table, 'user_id'))
                ->values()
                ->all();
        });
    }
}

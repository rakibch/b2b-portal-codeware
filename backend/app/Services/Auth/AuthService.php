<?php

namespace App\Services\Auth;

use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /** Pre-computed hash used to equalise timing when the email does not exist (anti-enumeration). */
    private const DUMMY_HASH = '$2y$12$IyAgDcwkTkS5CAdNGtuDMeLw8tKa1NmMkLGZ7rUgNBeZtMkv9t1ri';

    /**
     * @return array{user: User, token: string, expires_at: ?string}
     */
    public function login(string $email, #[\SensitiveParameter] string $password, string $deviceName, ?string $ip): array
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        $valid = $user !== null
            ? Hash::check($password, $user->password)
            : Hash::check($password, self::DUMMY_HASH) && false;

        if (! $valid) {
            AuditLog::record('auth.login_failed', $user, [], ['email' => mb_strtolower(trim($email))]);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            throw BusinessRuleException::make('account_inactive', [], 403);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->save();

        $token = $this->issueToken($user, $deviceName);
        AuditLog::record('auth.login', $user);

        return ['user' => $user] + $token;
    }

    /** @return array{token: string, expires_at: ?string} */
    public function issueToken(User $user, string $deviceName): array
    {
        $minutes = config('sanctum.expiration');
        $expiresAt = $minutes ? now()->addMinutes((int) $minutes) : null;

        $token = $user->createToken(mb_substr($deviceName, 0, 100), ['*'], $expiresAt);

        return ['token' => $token->plainTextToken, 'expires_at' => $expiresAt?->toIso8601String()];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function logoutEverywhere(User $user): void
    {
        $user->tokens()->delete();
    }

    /** Changes password and revokes all OTHER sessions/tokens. */
    public function changePassword(User $user, #[\SensitiveParameter] string $newPassword): void
    {
        $currentTokenId = $user->currentAccessToken()?->id;

        $user->forceFill(['password' => $newPassword])->save();
        $user->tokens()->when($currentTokenId, fn ($q) => $q->whereKeyNot($currentTokenId))->delete();

        AuditLog::record('auth.password_changed', $user);
    }
}

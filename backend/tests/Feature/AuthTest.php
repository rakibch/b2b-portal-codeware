<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
    }

    public function test_login_returns_token_user_and_permissions(): void
    {
        $user = $this->buyer();

        $this->postJson('/api/v1/auth/login', ['email' => strtoupper($user->email), 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'permissions', 'can_see_prices', 'company' => ['id', 'kyc']]])
            ->assertJsonPath('user.can_see_prices', true);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_and_unknown_email_return_identical_errors(): void
    {
        $user = $this->buyer();

        $wrong = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope'])->assertStatus(422)->json('errors');
        $unknown = $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'nope'])->assertStatus(422)->json('errors');

        $this->assertSame($wrong, $unknown);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->buyer();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'bad']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'bad'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests');
    }

    public function test_inactive_user_cannot_login_or_use_existing_token(): void
    {
        $user = $this->buyer();
        $token = $user->createToken('t')->plainTextToken;
        $user->forceFill(['is_active' => false])->save();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(403)->assertJsonPath('code', 'account_inactive');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(403);
    }

    public function test_accept_language_header_localises_responses(): void
    {
        $this->withHeader('Accept-Language', 'bn')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertHeader('Content-Language', 'bn')
            ->assertJsonPath('message', __('messages.errors.unauthenticated', [], 'bn'));
    }

    public function test_locale_preference_is_saved(): void
    {
        $user = $this->actingAsUser($this->buyer());

        $this->patchJson('/api/v1/auth/me/locale', ['locale' => 'bn'])->assertOk();
        $this->assertSame('bn', $user->fresh()->locale);

        $this->patchJson('/api/v1/auth/me/locale', ['locale' => 'fr'])->assertStatus(422);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->buyer();
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_change_password_revokes_other_tokens(): void
    {
        $user = $this->buyer();
        $user->createToken('other');
        $current = $user->createToken('current')->plainTextToken;

        $this->withToken($current)->putJson('/api/v1/auth/me/password', [
            'current_password' => 'password',
            'password' => 'NewPassw0rd',
            'password_confirmation' => 'NewPassw0rd',
        ])->assertOk();

        $this->assertSame(['current'], $user->tokens()->pluck('name')->all());
    }

    public function test_buyer_cannot_reach_admin_portal_and_staff_cannot_reach_buyer_portal(): void
    {
        $this->actingAsUser($this->buyer());
        $this->getJson('/api/v1/admin/kyc')->assertStatus(403);

        $this->actingAsUser($this->staff(Role::SuperAdmin));
        $this->getJson('/api/v1/buyer/cart')->assertStatus(403);
    }
}

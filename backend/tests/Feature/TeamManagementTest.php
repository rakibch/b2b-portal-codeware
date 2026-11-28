<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\CompanyInvitation;
use App\Notifications\CompanyInvitationNotification;
use App\Services\Company\CompanyUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
        Notification::fake();
    }

    public function test_invite_and_accept_creates_staff_with_approval_limit(): void
    {
        $admin = $this->actingAsUser($this->buyer());

        $this->postJson('/api/v1/buyer/team/invitations', [
            'email' => 'New.Officer@example.com',
            'role' => Role::BuyerStaff->value,
            'order_approval_limit' => '25000.00',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $token = null;
        Notification::assertSentTo(new AnonymousNotifiable, CompanyInvitationNotification::class, function ($n, $channels, $notifiable) use (&$token) {
            $token = (fn () => $this->plainToken)->call($n);

            return $notifiable->routes['mail'] === 'new.officer@example.com';
        });

        $invitation = CompanyInvitation::query()->firstOrFail();
        $this->assertNotSame($token, $invitation->token_hash); // only the hash is stored

        $this->getJson('/api/v1/auth/invitations?token='.$token)->assertOk()->assertJsonPath('email', 'new.officer@example.com');

        $response = $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token,
            'name' => 'New Officer',
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
        ])->assertCreated();

        $this->assertSame($admin->company->public_id, $response->json('user.company.id'));
        $this->assertSame('25000.00', $response->json('user.order_approval_limit'));

        // Token is single-use.
        $this->postJson('/api/v1/auth/invitations/accept', [
            'token' => $token, 'name' => 'X', 'password' => 'Secret1234', 'password_confirmation' => 'Secret1234',
        ])->assertStatus(410);
    }

    public function test_buyer_staff_cannot_invite(): void
    {
        $this->actingAsUser($this->buyer(null, Role::BuyerStaff));

        $this->postJson('/api/v1/buyer/team/invitations', ['email' => 'x@example.com', 'role' => 'buyer_staff'])->assertStatus(403);
    }

    public function test_admin_role_changes_respect_permissions_and_last_admin_guard(): void
    {
        $admin = $this->buyer();
        $second = $this->buyer($admin->company);
        $this->actingAsUser($admin);

        // Two admins: demoting the other one is allowed.
        $this->patchJson("/api/v1/buyer/team/members/{$second->id}", ['role' => 'buyer_staff'])->assertOk();
        $this->assertFalse($second->fresh()->isBuyerAdmin());

        // Demoted user can no longer manage members.
        $this->actingAsUser($second->fresh());
        $this->patchJson("/api/v1/buyer/team/members/{$admin->id}", ['is_active' => false])->assertStatus(403);

        // Service-level guard: the only active admin can never be demoted/deactivated.
        $this->expectException(BusinessRuleException::class);
        app(CompanyUserService::class)->updateMember($second->fresh(), $admin->fresh(), ['is_active' => false]);
    }

    public function test_deactivating_member_revokes_their_tokens(): void
    {
        $admin = $this->buyer();
        $member = $this->buyer($admin->company, Role::BuyerStaff);
        $member->createToken('x');
        $this->actingAsUser($admin);

        $this->patchJson("/api/v1/buyer/team/members/{$member->id}", ['is_active' => false])->assertOk();
        $this->assertSame(0, $member->tokens()->count());
    }
}

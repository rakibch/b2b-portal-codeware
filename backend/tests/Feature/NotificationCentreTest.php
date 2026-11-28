<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Notifications\KycApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPortal;
use Tests\TestCase;

class NotificationCentreTest extends TestCase
{
    use BuildsPortal, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortal();
    }

    public function test_user_sees_marks_and_clears_only_own_notifications(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();
        $buyer->notifyNow(new KycApprovedNotification($buyer->company), ['database']);
        $other->notifyNow(new KycApprovedNotification($other->company), ['database']);

        $this->actingAsUser($buyer);
        $id = $this->withHeader('Accept-Language', 'bn')->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('unread_count', 1)
            ->json('data.0.id');

        $foreign = $other->notifications()->first()->id;
        $this->postJson("/api/v1/notifications/{$foreign}/read")->assertStatus(404);

        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk()->assertJsonPath('unread_count', 0);
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('unread_count', 0);
    }

    public function test_announcements_are_audience_filtered_and_localised(): void
    {
        Announcement::query()->create(['title' => ['en' => 'Buyers only', 'bn' => 'শুধু ক্রেতা'], 'body' => ['en' => 'x'], 'audience' => 'buyers', 'is_published' => true]);
        Announcement::query()->create(['title' => ['en' => 'Staff only'], 'body' => ['en' => 'y'], 'audience' => 'staff', 'is_published' => true]);
        Announcement::query()->create(['title' => ['en' => 'Draft'], 'body' => ['en' => 'z'], 'audience' => 'all', 'is_published' => false]);

        $this->actingAsUser($this->buyer());
        $this->withHeader('Accept-Language', 'bn')->getJson('/api/v1/announcements')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'শুধু ক্রেতা');
    }
}

<?php

namespace App\Notifications;

use App\Models\CompanyInvitation;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent on-demand to an email address (no user yet). The plain token exists only in this message;
 * the queued payload is encrypted (ShouldBeEncrypted) so it never sits in plain text in the jobs table.
 */
class CompanyInvitationNotification extends BaseNotification implements ShouldBeEncrypted
{
    public function __construct(
        public readonly CompanyInvitation $invitation,
        #[\SensitiveParameter] private readonly string $plainToken,
    ) {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function key(): string
    {
        return 'company_invitation';
    }

    protected function replacements(): array
    {
        return [
            'company' => $this->invitation->company->name,
            'inviter' => $this->invitation->inviter->name,
            'hours' => (int) config('b2b.invitations.expires_hours'),
        ];
    }

    protected function path(): string
    {
        return '/accept-invitation?token='.urlencode($this->plainToken);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return parent::toMail($notifiable)->greeting(__('notifications.greeting', ['name' => $this->invitation->name ?? '']));
    }
}

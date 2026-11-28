<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued, bilingual (mail + database) notification. Locale is taken from the notifiable's
 * preferredLocale() (User implements HasLocalePreference), so __() renders in en or bn.
 * Subclasses provide translation key + replacements + an SPA path.
 */
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /** Translation key under lang/{locale}/notifications.php */
    abstract protected function key(): string;

    /** @return array<string, string|int> */
    abstract protected function replacements(): array;

    /** SPA-relative path the notification links to. */
    abstract protected function path(): string;

    /** Extra structured payload for the in-app notification centre. */
    protected function data(): array
    {
        return [];
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'notifications.'.$this->key();

        return (new MailMessage)
            ->subject(__($key.'.subject', $this->replacements()))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__($key.'.body', $this->replacements()))
            ->action(__($key.'.action'), $this->url())
            ->salutation(__('notifications.salutation', ['app' => config('app.name')]));
    }

    public function toArray(object $notifiable): array
    {
        $key = 'notifications.'.$this->key();

        return [
            'type' => $this->key(),
            'title' => __($key.'.subject', $this->replacements()),
            'body' => __($key.'.body', $this->replacements()),
            'path' => $this->path(),
        ] + $this->data();
    }

    protected function url(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$this->path();
    }
}

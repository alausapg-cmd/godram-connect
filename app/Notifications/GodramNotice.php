<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Services\WebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every notice GODRAM CONNECT sends. It always lands in the person's
 * notifications page; email and push follow their choices for the category.
 * In-app notices are written at once; email and push wait in the queue.
 */
class GodramNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $category,
        public string $title,
        public string $body,
        public ?string $url = null,
        public bool $important = false,
    ) {}

    public function via(User $user): array
    {
        $channels = ['database'];
        if (! $user->is_active) {
            return $channels;
        }
        // Sample members have made-up addresses, so they are never emailed. Push still works for them,
        // because it only reaches a phone someone deliberately switched on (as in the committee preview).
        if ($user->email && ! $user->member?->is_demo && $user->wantsNotice($this->category, 'email', $this->important)) {
            $channels[] = 'mail';
        }
        if (app(WebPush::class)->enabled() && $user->wantsNotice($this->category, 'push', $this->important) && $user->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function viaConnections(): array
    {
        $queue = config('godram.notify.queue');

        return ['database' => 'sync', 'mail' => $queue, WebPushChannel::class => $queue];
    }

    public function toArray(User $user): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'important' => $this->important,
        ];
    }

    public function toMail(User $user): MailMessage
    {
        $first = $user->member?->first_name ?? strtok((string) $user->name, ' ');

        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.$first.',')
            ->line($this->body)
            ->when($this->url, fn ($m) => $m->action('Open in GODRAM CONNECT', $this->url))
            ->line('You can choose which messages reach you by email under Notification settings.')
            ->salutation('GODRAM CONNECT');
    }

    public function toWebPush(User $user): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url ?? route('notifications.index'),
            'tag' => $this->category,
            'icon' => asset('icons/icon-192.png'),
            'badge' => asset('icons/icon-192.png'),
        ];
    }
}

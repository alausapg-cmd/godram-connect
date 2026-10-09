<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush as Sender;
use Psr\Log\NullLogger;

/**
 * Sends phone and browser notifications with the standard Web Push protocol
 * (no third-party account needed). Works without the GMP or BCMath PHP
 * extensions, only more slowly; the server keys live in .env.
 */
class WebPush
{
    public function enabled(): bool
    {
        return filled(config('godram.push.public_key')) && filled(config('godram.push.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('godram.push.public_key') ?: null;
    }

    /** @return array{publicKey: string, privateKey: string} */
    public static function newKeys(): array
    {
        return VAPID::createVapidKeys();
    }

    /**
     * Sends one payload to each subscription. Subscriptions the push service
     * says no longer exist are deleted. Returns how many were delivered.
     *
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    public function send(Collection $subscriptions, array $payload): int
    {
        if (! $this->enabled() || $subscriptions->isEmpty()) {
            return 0;
        }

        // The library only logs a suggestion to install GMP or BCMath, which this host may not have.
        $sender = new Sender(auth: ['VAPID' => [
            'subject' => config('godram.push.subject'),
            'publicKey' => config('godram.push.public_key'),
            'privateKey' => config('godram.push.private_key'),
        ]], defaultOptions: ['TTL' => 86400, 'urgency' => 'normal'], logger: new NullLogger);
        $sender->setReuseVAPIDHeaders(true);

        $byEndpoint = $subscriptions->keyBy('endpoint');
        $body = json_encode($payload);
        foreach ($subscriptions as $s) {
            $sender->queueNotification(Subscription::create([
                'endpoint' => $s->endpoint,
                'publicKey' => $s->public_key,
                'authToken' => $s->auth_token,
                'contentEncoding' => $s->content_encoding,
            ]), $body);
        }

        $delivered = 0;
        foreach ($sender->flush() as $report) {
            $subscription = $byEndpoint->get($report->getEndpoint());
            if ($report->isSuccess()) {
                $delivered++;
                $subscription?->forceFill(['last_used_at' => now()])->save();
            } elseif ($report->isSubscriptionExpired()) {
                $subscription?->delete();
            } else {
                Log::warning('Push notification not delivered: '.$report->getReason());
            }
        }

        return $delivered;
    }
}

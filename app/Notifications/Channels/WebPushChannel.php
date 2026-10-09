<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\GodramNotice;
use App\Services\WebPush;

class WebPushChannel
{
    public function __construct(protected WebPush $push) {}

    public function send(User $user, GodramNotice $notice): void
    {
        $this->push->send($user->pushSubscriptions()->get(), $notice->toWebPush($user));
    }
}

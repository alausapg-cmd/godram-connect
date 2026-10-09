<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Services\WebPush;
use Illuminate\Http\Request;

/** A person's notices, and how they want to receive them. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('notifications.index', [
            'notifications' => $user->notifications()->paginate(25),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Marks the notice read, then goes where it points. Only links to this site are followed. */
    public function open(Request $request, string $id)
    {
        $notice = $request->user()->notifications()->findOrFail($id);
        $notice->markAsRead();

        return redirect()->to($this->sameSite($notice->data['url'] ?? null) ?? route('notifications.index'));
    }

    /**
     * Notices made by cron carry the configured address (APP_URL), which may differ from
     * the one being browsed (http or https, with or without www). Only our own hosts are
     * accepted, and the person stays on the address they are using.
     */
    protected function sameSite(?string $url): ?string
    {
        $parts = $url ? parse_url($url) : false;
        if (! $parts || ! isset($parts['host'])) {
            return null;
        }
        $ours = array_filter([request()->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]);
        $bare = fn ($host) => preg_replace('/^www\./', '', strtolower($host));
        if (! in_array($bare($parts['host']), array_map($bare, $ours), true)) {
            return null;
        }

        return url(($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : ''));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All caught up.');
    }

    public function settings(Request $request, WebPush $push)
    {
        $user = $request->user()->load('notificationPreferences');

        return view('notifications.settings', [
            'user' => $user,
            'categories' => config('notifications.categories'),
            'pushReady' => $push->enabled(),
            'pushKey' => $push->publicKey(),
            'devices' => $user->pushSubscriptions()->latest()->get(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $user = $request->user();
        $chosen = $request->validate(['email' => 'array', 'push' => 'array']);

        foreach (config('notifications.categories') as $key => $category) {
            if ($category['locked'] ?? false) {
                continue;
            }
            $email = isset($chosen['email'][$key]);
            $push = isset($chosen['push'][$key]);
            if ($email === (bool) $category['email'] && $push === (bool) $category['push']) {
                NotificationPreference::where('user_id', $user->id)->where('category', $key)->delete();
            } else {
                NotificationPreference::updateOrCreate(['user_id' => $user->id, 'category' => $key], ['email' => $email, 'push' => $push]);
            }
        }

        return back()->with('status', 'Your notification choices are saved.');
    }

    /** Called by the browser after the person allows notifications on this device. */
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|url:https|max:1000',
            'keys.p256dh' => 'required|string|max:200',
            'keys.auth' => 'required|string|max:100',
            'encoding' => 'nullable|in:aes128gcm,aesgcm',
        ]);

        PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['encoding'] ?? 'aes128gcm',
            'device' => $this->deviceName((string) $request->userAgent()),
        ]);

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required_without:id|string', 'id' => 'nullable|integer']);
        $request->user()->pushSubscriptions()
            ->when($request->filled('id'), fn ($q) => $q->whereKey($request->integer('id')))
            ->when(! $request->filled('id'), fn ($q) => $q->where('endpoint_hash', hash('sha256', (string) $request->input('endpoint'))))
            ->delete();

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', 'That device will no longer get notifications.');
    }

    protected function deviceName(string $agent): string
    {
        $system = match (true) {
            str_contains($agent, 'Android') => 'Android phone',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Windows') => 'Windows computer',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux computer',
            default => 'Device',
        };
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => null,
        };

        return $system.($browser ? ' · '.$browser : '');
    }
}

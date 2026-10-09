<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SentReminder;
use App\Models\Video;
use App\Services\Access;
use App\Services\WebPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Which outside services GODRAM CONNECT really works with, in plain words.
 * The spec asks us never to claim an integration the platform does not allow,
 * so each one says exactly what happens and what does not.
 */
class IntegrationController extends Controller
{
    public const FULL = 'Fully integrated';

    public const PARTIAL = 'Partly integrated';

    public const SHARE = 'Share and export';

    public const OFF = 'Not set up yet';

    public function index(Request $request, Access $access, WebPush $push)
    {
        $user = $request->user();
        abort_unless($access->can($user, 'settings.manage') || $access->can($user, 'media.manage'), 403);

        $mailer = config('mail.default');
        $mailReady = ! in_array($mailer, ['log', 'array'], true);
        $lastImport = Video::whereNotNull('youtube_id')->where('submitted_by', null)->max('created_at');

        $integrations = [
            ['GODRAM TV on YouTube', 'play', self::PARTIAL,
                'New uploads on the GODRAM TV channel are added to the Watch centre every hour and play inside GODRAM CONNECT.'.($lastImport ? ' Last import: '.\Illuminate\Support\Carbon::parse($lastImport)->diffForHumans().'.' : ''),
                'Uploading to YouTube and view counts stay in YouTube Studio. Reading views would need a YouTube Data API key.'],
            ['YouTube Live and Facebook Live', 'live', self::PARTIAL,
                'Paste the livestream link on an event: the stream plays on the event page, members are told when it is about to start, and the replay is kept.',
                'The stream itself is started from YouTube Studio or Facebook.'],
            ['Email', 'message', $mailReady ? self::FULL : self::OFF,
                $mailReady ? 'Important notices, password resets and sign-in links are emailed through '.strtoupper($mailer).'.' : 'Emails are written to the log file instead of being sent.',
                $mailReady ? 'Each person chooses which notices they get by email.' : 'Add the hosting email account (MAIL_ settings in .env). See the install guide.'],
            ['Phone and browser notifications', 'bell', $push->enabled() ? self::FULL : self::OFF,
                $push->enabled() ? 'Members who switch them on get notices on their phone, even when the app is closed. No outside account or fee.' : 'The server keys have not been created.',
                $push->enabled() ? 'On iPhone, members first add GODRAM CONNECT to the Home Screen.' : 'Run php artisan godram:push-keys once on the server.'],
            ['WhatsApp', 'whatsapp', self::SHARE,
                'Events, announcements and training have a Create, Preview, Share panel: the message and picture are prepared, and WhatsApp opens ready to send to a group, channel or Status.',
                'Posting into groups automatically is not possible: WhatsApp does not allow it. The paid WhatsApp Business API sends only one-to-one template messages.'],
            ['Facebook', 'globe', self::SHARE,
                'Shared links show a GODRAM picture card with the title and date.',
                'Posting straight to the GODRAM Page would need a Meta app approved by Facebook.'],
            ['Instagram and TikTok', 'image', self::SHARE,
                'Download or share the picture card from the share panel and paste the prepared caption.',
                'Neither allows posting from another website without a business review.'],
            ['X (Twitter)', 'share', self::SHARE, 'Opens X with the message and link filled in.', 'Nothing is posted automatically.'],
            ['Zoom, Google Meet and Microsoft Teams', 'users', self::PARTIAL,
                'Live classes and online events link to the meeting. When a member opens it from GODRAM CONNECT they are recorded as "joined"; a facilitator confirms who attended.',
                'Meetings are created in Zoom, Meet or Teams. Their own attendance reports are not read.'],
            ['Calendars', 'calendar', self::SHARE, 'Every event can be added to Google Calendar or downloaded for any phone calendar.', 'Calendars are not kept in sync after changes; people see changes on the event page and in notices.'],
        ];

        return view('admin.integrations', [
            'integrations' => $integrations,
            'queue' => [
                'connection' => config('godram.notify.queue'),
                'waiting' => rescue(fn () => DB::table('jobs')->count(), null, false),
                'failed' => rescue(fn () => DB::table('failed_jobs')->count(), null, false),
                'last_reminder' => SentReminder::max('created_at'),
            ],
        ]);
    }
}

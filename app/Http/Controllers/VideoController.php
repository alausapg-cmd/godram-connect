<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Production;
use App\Models\Spotlight;
use App\Models\Video;
use App\Services\Access;
use App\Services\AuditLogger;
use App\Services\YouTubeMeta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The Watch centre (GODRAM TV) and the screens that keep it stocked. */
class VideoController extends Controller
{
    public function __construct(protected Access $access, protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $category = $request->input('category');
        $category = isset(Video::CATEGORIES[$category]) ? $category : null;

        $events = Event::visibleTo($user)->streamed()->upcoming()->limit(6)->get();
        $featured = Spotlight::currentSubject('performance_of_week')
            ?? Video::published()->where('is_featured', true)->latest('published_at')->first()
            ?? Video::published()->latest('published_at')->first();

        return view('watch.index', [
            'category' => $category,
            'featured' => $category ? null : $featured,
            'live' => $events->filter->isLive()->values(),
            'upcomingLive' => $events->reject->isLive()->values(),
            'replays' => Event::visibleTo($user)->past()->whereNotNull('replay_youtube_id')->limit(4)->get(),
            'rows' => $category
                ? collect([$category => Video::published()->where('category', $category)->latest('published_at')->get()])
                : Video::published()->latest('published_at')->get()->groupBy('category')
                    ->sortBy(fn ($v, $key) => array_search($key, array_keys(Video::CATEGORIES))),
            'counts' => Video::published()->selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category'),
            'canSubmit' => $this->access->can($user, 'media.submit'),
            'canManage' => $this->access->can($user, 'media.manage'),
        ]);
    }

    public function show(Request $request, Video $video)
    {
        $user = $request->user();
        abort_unless($video->status === 'published' || $this->access->can($user, 'media.manage') || $video->submitted_by === $user?->id, 404);
        $video->load(['event', 'production', 'orgUnit']);

        $next = Video::published()->whereKeyNot($video->id)
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$video->category])
            ->latest('published_at')->limit(8)->get();

        return view('watch.show', [
            'video' => $video,
            'next' => $next,
            'canManage' => $this->access->can($user, 'media.manage'),
            'isSpotlight' => Spotlight::current('performance_of_week')->where('subject_type', 'video')->where('subject_id', $video->id)->exists(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($this->access->can($request->user(), 'media.submit'), 403);

        return $this->form($request, new Video(['category' => 'drama_performances']));
    }

    public function store(Request $request, YouTubeMeta $meta)
    {
        $user = $request->user();
        abort_unless($this->access->can($user, 'media.submit'), 403);
        $data = $this->validated($request);
        if (blank($data['title'])) {
            $data['title'] = $meta->lookup($data['youtube_id'])['title'] ?? null;
            if (blank($data['title'])) {
                throw ValidationException::withMessages(['title' => 'Give the video a title.']);
            }
        }

        $publish = $this->access->can($user, 'media.manage');
        $video = Video::create($data + [
            'submitted_by' => $user->id,
            'status' => $publish ? 'published' : 'submitted',
            'published_at' => $publish ? now() : null,
            'reviewed_by' => $publish ? $user->id : null,
            'org_unit_id' => $this->access->units($user, 'media.submit')->first()?->id,
        ]);
        $this->audit->log($publish ? 'video.published' : 'video.submitted', $video, ($publish ? 'Video published: ' : 'Video suggested: ').$video->title);

        return $publish
            ? redirect()->route('watch.show', $video)->with('status', 'The video is now in the Watch centre.')
            : redirect()->route('videos.manage')->with('status', 'Thank you. The national media team will check the video and add it to the Watch centre.');
    }

    public function edit(Request $request, Video $video)
    {
        abort_unless($this->access->can($request->user(), 'media.manage'), 403);

        return $this->form($request, $video);
    }

    public function update(Request $request, Video $video)
    {
        abort_unless($this->access->can($request->user(), 'media.manage'), 403);
        $video->update(array_filter($this->validated($request, $video), fn ($v, $k) => $k !== 'title' || filled($v), ARRAY_FILTER_USE_BOTH));

        return redirect()->route('watch.show', $video)->with('status', 'Video details saved.');
    }

    public function manage(Request $request)
    {
        $user = $request->user();
        abort_unless($this->access->can($user, 'media.submit'), 403);
        $canManage = $this->access->can($user, 'media.manage');

        return view('watch.manage', [
            'pending' => $canManage ? Video::where('status', 'submitted')->with('submitter')->oldest()->get() : collect(),
            'mine' => Video::where('submitted_by', $user->id)->latest()->limit(20)->get(),
            'recent' => $canManage ? Video::published()->latest('published_at')->limit(30)->get() : collect(),
            'canManage' => $canManage,
        ]);
    }

    public function decide(Request $request, Video $video)
    {
        $user = $request->user();
        abort_unless($this->access->can($user, 'media.manage'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['publish', 'reject', 'archive', 'feature', 'spotlight'])],
            'reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:250'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        match ($data['decision']) {
            'publish' => $video->forceFill(['status' => 'published', 'published_at' => $video->published_at ?? now(), 'reviewed_by' => $user->id, 'reject_reason' => null])->save(),
            'reject' => $video->forceFill(['status' => 'rejected', 'reviewed_by' => $user->id, 'reject_reason' => $data['reason']])->save(),
            'archive' => $video->forceFill(['status' => 'archived', 'is_featured' => false])->save(),
            'feature' => $video->forceFill(['is_featured' => ! $video->is_featured])->save(),
            'spotlight' => Spotlight::create([
                'kind' => 'performance_of_week', 'subject_type' => 'video', 'subject_id' => $video->id,
                'note' => $data['note'] ?? null, 'starts_on' => today(), 'ends_on' => today()->addDays(6), 'created_by' => $user->id,
            ]),
        };
        $this->audit->log('video.'.$data['decision'], $video, ucfirst($data['decision']).': '.$video->title, array_filter(['reason' => $data['reason'] ?? null]));

        return back()->with('status', match ($data['decision']) {
            'publish' => 'Published to the Watch centre.',
            'reject' => 'Returned to the person who suggested it.',
            'archive' => 'Removed from the Watch centre. It stays in the records.',
            'feature' => $video->is_featured ? 'Featured on the Watch centre.' : 'No longer featured.',
            'spotlight' => 'This is now the Performance of the Week, until '.today()->addDays(6)->format('j M').'.',
        });
    }

    protected function form(Request $request, Video $video)
    {
        return view('watch.form', [
            'video' => $video,
            'events' => Event::visibleTo($request->user())->orderByDesc('starts_at')->limit(50)->get(['id', 'title', 'starts_at']),
            'productions' => Production::published()->orderBy('title')->get(['id', 'title']),
            'canManage' => $this->access->can($request->user(), 'media.manage'),
        ]);
    }

    protected function validated(Request $request, ?Video $video = null): array
    {
        $data = $request->validate([
            'youtube_url' => [$video ? 'nullable' : 'required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category' => ['required', Rule::in(array_keys(Video::CATEGORIES))],
            'recorded_on' => ['nullable', 'date', 'before_or_equal:today'],
            'event_id' => ['nullable', 'exists:events,id'],
            'production_id' => ['nullable', 'exists:productions,id'],
        ], ['youtube_url.required' => 'Paste the YouTube link.']);

        if (filled($data['youtube_url'] ?? null)) {
            $id = Video::youtubeIdFrom($data['youtube_url']);
            if (! $id) {
                throw ValidationException::withMessages(['youtube_url' => 'That does not look like a YouTube video link. Copy it from the Share button on YouTube.']);
            }
            if (Video::where('youtube_id', $id)->when($video, fn ($q) => $q->whereKeyNot($video->id))->exists()) {
                throw ValidationException::withMessages(['youtube_url' => 'This video is already in the Watch centre.']);
            }
            $data['youtube_id'] = $id;
        }
        unset($data['youtube_url']);

        return $data;
    }
}

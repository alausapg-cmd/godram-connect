<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Production;
use App\Models\Story;
use App\Models\Video;
use App\Services\Access;
use App\Services\ImageStore;
use App\Services\StoryWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** GODRAM Stories: testimonies, impact, production and member stories. */
class StoryController extends Controller
{
    public function __construct(protected Access $access, protected StoryWorkflow $workflow) {}

    public function index(Request $request)
    {
        $type = $request->input('type');
        $type = isset(Story::TYPES[$type]) ? $type : null;
        $stories = Story::published()->with('author', 'orgUnit')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderByDesc('is_featured')->latest('published_at')->paginate(13)->withQueryString();

        return view('stories.index', [
            'stories' => $stories,
            'type' => $type,
            'types' => Story::published()->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type'),
        ]);
    }

    public function show(Request $request, Story $story)
    {
        $user = $request->user();
        abort_unless($story->isVisibleTo($user), 404);
        $story->load(['author.member', 'event', 'production', 'orgUnit', 'reviewer']);

        return view('stories.show', [
            'story' => $story,
            'more' => Story::published()->whereKeyNot($story->id)
                ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [$story->type])->latest('published_at')->limit(3)->get(),
            'canReview' => $this->workflow->canReview($user, $story),
            'canEdit' => $this->workflow->canEdit($user, $story),
        ]);
    }

    public function audio(Request $request, Story $story)
    {
        abort_unless($story->audio_path && $story->isVisibleTo($request->user()) && Storage::disk('local')->exists($story->audio_path), 404);

        return response()->file(Storage::disk('local')->path($story->audio_path), ['Cache-Control' => 'public, max-age=86400']);
    }

    public function mine(Request $request)
    {
        return view('stories.mine', [
            'stories' => Story::where('author_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->member_id, 403);

        return $this->form($request, new Story(['type' => 'testimony']));
    }

    public function store(Request $request, ImageStore $images)
    {
        $user = $request->user();
        abort_unless($user->member_id, 403);
        $story = Story::create($this->validated($request) + [
            'author_id' => $user->id,
            'org_unit_id' => $user->member?->assembly()?->id,
            'status' => 'draft',
        ]);

        return $this->finish($request, $story, $images);
    }

    public function edit(Request $request, Story $story)
    {
        abort_unless($this->workflow->canEdit($request->user(), $story), 403);

        return $this->form($request, $story);
    }

    public function update(Request $request, Story $story, ImageStore $images)
    {
        abort_unless($this->workflow->canEdit($request->user(), $story), 403);
        $story->update($this->validated($request));

        return $this->finish($request, $story, $images);
    }

    public function manage(Request $request)
    {
        abort_unless($this->access->can($request->user(), 'stories.review'), 403);

        return view('stories.manage', [
            'queue' => Story::whereIn('status', ['submitted', 'under_review', 'approved'])->with('author')->orderBy('submitted_at')->get(),
            'published' => Story::published()->latest('published_at')->limit(30)->get(),
        ]);
    }

    public function review(Request $request, Story $story)
    {
        abort_unless($this->workflow->canReview($request->user(), $story), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['start', 'approve', 'publish', 'reject', 'archive', 'feature'])],
            'note' => ['nullable', 'required_if:decision,reject', 'string', 'max:500'],
        ], ['note.required_if' => 'Tell the writer what to change.']);
        $this->workflow->decide($story, $request->user(), $data['decision'], $data['note'] ?? null);

        return back()->with('status', match ($data['decision']) {
            'start' => 'Marked as under review.',
            'approve' => 'Approved. Publish it when you are ready.',
            'publish' => 'Published. The story is now on the Stories page.',
            'reject' => 'Returned to the writer with your note.',
            'archive' => 'Archived.',
            'feature' => $story->is_featured ? 'This is now the featured story.' : 'No longer featured.',
        });
    }

    protected function finish(Request $request, Story $story, ImageStore $images)
    {
        if ($request->hasFile('cover')) {
            $story->forceFill(['cover_path' => $images->storeImage($request->file('cover'), 'stories', 1800)])->save();
        }
        if ($request->hasFile('audio')) {
            $story->forceFill(['audio_path' => $images->storeDocument($request->file('audio'), 'stories/audio')])->save();
        }

        if ($request->input('action') === 'submit') {
            $this->workflow->submit($story->fresh(), $request->user());

            return redirect()->route('stories.mine')->with('status', 'Thank you for sharing. The national team will read your story and let you know when it is published.');
        }

        return redirect()->route('stories.edit', $story)->with('status', 'Draft saved. Send it for review when it is ready.');
    }

    protected function form(Request $request, Story $story)
    {
        return view('stories.form', [
            'story' => $story,
            'events' => Event::visibleTo($request->user())->orderByDesc('starts_at')->limit(50)->get(['id', 'title', 'starts_at']),
            'productions' => Production::published()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Story::TYPES))],
            'standfirst' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:20000'],
            'quote' => ['nullable', 'string', 'max:400'],
            'quote_by' => ['nullable', 'string', 'max:120'],
            'youtube_url' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'exists:events,id'],
            'production_id' => ['nullable', 'exists:productions,id'],
            'cover' => ['nullable', 'image', 'max:'.config('godram.uploads.image_max_kb')],
            'audio' => ['nullable', 'file', 'mimes:mp3,m4a,ogg,wav,aac', 'max:20480'],
        ]);

        $data['youtube_id'] = null;
        if (filled($data['youtube_url'] ?? null)) {
            $data['youtube_id'] = Video::youtubeIdFrom($data['youtube_url']);
            if (! $data['youtube_id']) {
                throw ValidationException::withMessages(['youtube_url' => 'Paste a YouTube video link.']);
            }
        }

        return collect($data)->except(['youtube_url', 'cover', 'audio'])->all();
    }
}

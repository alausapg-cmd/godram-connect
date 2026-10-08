<?php

namespace App\Http\Controllers;

use App\Models\ActivityReport;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Member;
use App\Models\Production;
use App\Models\Story;
use App\Models\Video;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * One search box across the platform. Guests search public content; signed-in
 * people also find members and reports, but only inside their own branch.
 */
class SearchController extends Controller
{
    public const SECTIONS = [
        'events' => 'Events',
        'videos' => 'Videos',
        'stories' => 'Stories',
        'showcase' => 'Showcase',
        'highlights' => 'Highlights',
        'news' => 'News',
        'archive' => 'Archive',
        'members' => 'Members',
        'reports' => 'Reports',
    ];

    public function __construct(protected Access $access) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $term = trim((string) $request->input('q'));
        $year = $request->integer('year') ?: null;
        $only = isset(self::SECTIONS[$request->input('in')]) ? $request->input('in') : null;
        $results = collect();

        if (mb_strlen($term) >= 2 || $year) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $match = fn ($q, array $columns) => $term === '' ? $q : $q->where(function ($q) use ($columns, $like) {
                foreach ($columns as $c) {
                    $q->orWhere($c, 'like', $like);
                }
            });
            $want = fn (string $section) => ! $only || $only === $section;

            if ($want('events')) {
                $results['events'] = $match(Event::visibleTo($user), ['title', 'description', 'location'])
                    ->when($year, fn ($q) => $q->whereYear('starts_at', $year))->orderByDesc('starts_at')->limit(8)->get()
                    ->map(fn ($e) => $this->row($e->title, route('events.show', $e), $e->typeLabel().' · '.$e->starts_at->format('j M Y').' · '.$e->organiserName(), 'calendar'));
            }
            if ($want('videos')) {
                $results['videos'] = $match(Video::published(), ['title', 'description'])
                    ->when($year, fn ($q) => $q->whereYear('published_at', $year))->latest('published_at')->limit(8)->get()
                    ->map(fn ($v) => $this->row($v->title, route('watch.show', $v), $v->categoryLabel(), 'play'));
            }
            if ($want('stories')) {
                $results['stories'] = $match(Story::published(), ['title', 'standfirst', 'body'])
                    ->when($year, fn ($q) => $q->whereYear('published_at', $year))->latest('published_at')->limit(8)->get()
                    ->map(fn ($s) => $this->row($s->title, route('stories.show', $s), $s->typeLabel(), 'report'));
            }
            if ($want('showcase')) {
                $results['showcase'] = $match(Production::published(), ['title', 'summary', 'body', 'credits'])
                    ->when($year, fn ($q) => $q->where('year', $year))->orderByDesc('year')->limit(8)->get()
                    ->map(fn ($p) => $this->row($p->title, route('showcase.show', $p), $p->kindLabel().($p->year ? ' · '.$p->year : ''), 'film'));
            }
            if ($want('highlights')) {
                $results['highlights'] = $match(ActivityReport::where('status', 'published'), ['title', 'event_name', 'description', 'location'])
                    ->when($year, fn ($q) => $q->whereYear('activity_date', $year))->with('orgUnit')->latest('activity_date')->limit(8)->get()
                    ->map(fn ($r) => $this->row($r->displayTitle(), route('highlights.show', $r), $r->typeLabel().' · '.$r->orgUnit?->name.' · '.$r->activity_date->format('j M Y'), 'award'));
            }
            if ($want('news')) {
                $results['news'] = $match(Announcement::live()->visibleTo($user), ['title', 'body'])
                    ->when($year, fn ($q) => $q->whereYear('published_at', $year))->latest('published_at')->limit(8)->get()
                    ->map(fn ($a) => $this->row($a->title, route('announcements.show', $a), $a->published_at?->format('j M Y'), 'megaphone'));
            }
            if ($want('archive')) {
                $results['archive'] = collect(config('archive'))
                    ->filter(fn ($i) => ($term === '' || mb_stripos($i['title'].' '.$i['caption'], $term) !== false) && (! $year || $i['year'] === $year))
                    ->map(fn ($i) => $this->row($i['title'], route('archive').'#'.pathinfo($i['file'], PATHINFO_FILENAME), $i['caption'].($i['year'] ? ' · '.$i['year'] : ''), 'history'))
                    ->values();
            }
            if ($want('members') && $term !== '' && ($paths = $this->access->paths($user, 'members.view'))) {
                $results['members'] = Member::placedWithinPaths($paths)->search($term)->with('currentPlacement.orgUnit')->limit(8)->get()
                    ->map(fn ($m) => $this->row($m->full_name, route('members.show', $m), $m->member_no.' · '.$m->currentPlacement?->orgUnit?->name, 'user'));
            }
            if ($want('reports') && ($paths = $this->access->paths($user, 'reports.view'))) {
                $results['reports'] = $match(ActivityReport::withinPaths($paths)->where('status', '!=', 'draft'), ['title', 'reference', 'event_name', 'location'])
                    ->when($year, fn ($q) => $q->whereYear('activity_date', $year))->with('orgUnit')->latest('activity_date')->limit(8)->get()
                    ->map(fn ($r) => $this->row($r->displayTitle(), route('reports.show', $r), $r->reference.' · '.$r->orgUnit?->name.' · '.$r->statusLabel(), 'report'));
            }
        }

        $results = $results->filter(fn (Collection $rows) => $rows->isNotEmpty());

        return view('search', [
            'term' => $term,
            'year' => $year,
            'only' => $only,
            'results' => $results,
            'total' => $results->sum(fn ($rows) => $rows->count()),
        ]);
    }

    protected function row(string $title, string $url, ?string $meta, string $icon): object
    {
        return (object) compact('title', 'url', 'meta', 'icon');
    }
}

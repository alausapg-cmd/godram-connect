<?php

namespace App\Http\Controllers;

use App\Models\ActivityReport;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Spotlight;
use App\Models\Story;
use App\Models\Video;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home(Request $request)
    {
        $user = $request->user();
        $upcoming = Event::visibleTo($user)->where('status', 'published')->upcoming()->with('orgUnit.parent')->limit(6)->get();
        $facts = config('godram.did_you_know', []);

        return view('public.home', [
            'live' => $upcoming->first(fn ($e) => $e->isLive()),
            'upcoming' => $upcoming->reject->isLive()->take(3)->values(),
            'performance' => Spotlight::currentSubject('performance_of_week'),
            'story' => Story::published()->orderByDesc('is_featured')->latest('published_at')->first(),
            'videos' => Video::published()->latest('published_at')->limit(8)->get(),
            'fact' => $facts ? $facts[now()->dayOfYear % count($facts)] : null,
            'announcements' => Announcement::live()->public()->orderByDesc('is_pinned')->latest('published_at')->limit(3)->get(),
            'highlights' => ActivityReport::where('status', 'published')->with(['orgUnit.parent', 'media'])->latest('published_at')->limit(3)->get(),
            'archive' => collect(config('archive'))->take(8),
            'counts' => [
                'regions' => OrgUnit::ofType(OrgUnit::REGION)->where('is_active', true)->count(),
                'districts' => OrgUnit::ofType(OrgUnit::DISTRICT)->where('is_active', true)->count(),
                'assemblies' => OrgUnit::ofType(OrgUnit::ASSEMBLY)->where('is_active', true)->count(),
                'members' => Member::approved()->where('status', 'active')->count(),
            ],
        ]);
    }

    public function about()
    {
        return view('public.about');
    }

    public function archive()
    {
        return view('public.archive', ['items' => collect(config('archive'))]);
    }

    public function highlights()
    {
        return view('public.highlights', [
            'reports' => ActivityReport::where('status', 'published')->with(['orgUnit.parent', 'media'])->latest('published_at')->paginate(12),
        ]);
    }

    public function highlight(ActivityReport $report)
    {
        abort_unless($report->status === 'published', 404);

        return view('public.highlight', ['report' => $report->load('orgUnit.parent', 'media')]);
    }



}

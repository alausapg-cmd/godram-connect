<?php

namespace App\Http\Controllers;

use App\Models\ActivityReport;
use App\Models\Announcement;
use App\Models\Member;
use App\Models\OrgUnit;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home(Request $request)
    {
        return view('public.home', [
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

    public function watch()
    {
        return view('public.coming', [
            'title' => 'Watch',
            'eyebrow' => 'GODRAM TV',
            'lead' => 'Films, drama performances, live broadcasts and behind-the-scenes stories, all in one place.',
            'body' => 'The Watch centre is being built in the next phase. Until then, every GODRAM TV video is on YouTube.',
            'cta' => ['label' => 'Watch GODRAM TV on YouTube', 'url' => config('godram.links.youtube')],
        ]);
    }

    public function events()
    {
        return view('public.coming', [
            'title' => 'Events',
            'eyebrow' => 'Performances, trainings and programmes',
            'lead' => 'Find GODRAM performances, workshops, conventions and premieres near you.',
            'body' => 'The events calendar arrives in the next phase. Meanwhile, the latest news carries upcoming programmes.',
            'cta' => ['label' => 'See the latest news', 'url' => route('announcements.index')],
        ]);
    }

    public function academy()
    {
        return view('public.coming', [
            'title' => 'GODRAM Virtual Academy',
            'eyebrow' => 'Learn. Practise. Be certified.',
            'lead' => 'The digital home of GODRAM training: courses, live classes, examinations and verifiable certificates.',
            'body' => 'The GODRAM Virtual Academy carries forward the work of the GODRAM Institute of Christian Drama, whose first class of 41 drama ministers graduated in 1995. It opens in a later phase of GODRAM CONNECT.',
            'cta' => ['label' => 'Read the GODRAM story', 'url' => route('about')],
        ]);
    }
}

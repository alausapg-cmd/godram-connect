<?php

namespace App\Http\Controllers;

use App\Models\ActivityReport;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Services\Access;
use Illuminate\Http\Request;

/**
 * The GODRAM Network Explorer: National → Regions → Districts → Assemblies.
 * The public sees the structure and published highlights; member counts and
 * member lists appear only for people allowed to view members there.
 */
class NetworkController extends Controller
{
    public function show(Request $request, Access $access, ?OrgUnit $unit = null)
    {
        $unit ??= OrgUnit::root();
        abort_unless($unit && $unit->is_active, 404);
        $user = $request->user();
        $canSeeMembers = $access->can($user, 'members.view', $unit);

        $children = $unit->children()->where('is_active', true)->withCount(['children' => fn ($q) => $q->where('is_active', true)])->get()
            ->map(function (OrgUnit $child) use ($canSeeMembers) {
                $child->member_total = $canSeeMembers ? Member::approved()->where('status', 'active')->placedWithin($child)->count() : null;

                return $child;
            });

        return view('network.show', [
            'unit' => $unit,
            'ancestors' => $unit->ancestors(),
            'children' => $children,
            'canSeeMembers' => $canSeeMembers,
            'memberTotal' => $canSeeMembers ? Member::approved()->where('status', 'active')->placedWithin($unit)->count() : null,
            'highlights' => ActivityReport::where('status', 'published')->withinPaths([$unit->path])->with('orgUnit', 'media')->latest('published_at')->limit(3)->get(),
            'reportCount' => ActivityReport::withinPaths([$unit->path])->counted()->where('activity_date', '>=', now()->subYear()->toDateString())->count(),
        ]);
    }
}

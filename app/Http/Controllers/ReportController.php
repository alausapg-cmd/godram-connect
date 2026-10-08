<?php

namespace App\Http\Controllers;

use App\Models\ActivityReport;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Services\Access;
use App\Services\AuditLogger;
use App\Services\ReportWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(
        protected Access $access,
        protected ReportWorkflow $workflow,
        protected AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $paths = $this->access->paths($user, 'reports.view');
        abort_if(empty($paths), 403);

        $tab = $request->input('tab', 'all');
        $reports = ActivityReport::query()
            ->withinPaths($paths)
            ->when($tab === 'review', fn ($q) => $q->whereIn('status', ['submitted', 'under_review'])->where('created_by', '!=', $user->id))
            ->when($tab === 'mine', fn ($q) => $q->where('created_by', $user->id))
            ->when($tab === 'all', fn ($q) => $q->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhere('created_by', $user->id)))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('type'), fn ($q, $t) => $q->where('activity_type', $t))
            ->when($request->input('q'), fn ($q, $term) => $q->where(fn ($q) => $q->where('title', 'like', "%$term%")->orWhere('reference', 'like', "%$term%")))
            ->with(['orgUnit.parent', 'creator'])
            ->orderByRaw("CASE WHEN status IN ('submitted','under_review') THEN 0 ELSE 1 END")
            ->latest('activity_date')
            ->paginate(20)->withQueryString();

        if ($tab === 'review') {
            $reports->setCollection($reports->getCollection()->filter(fn ($r) => $this->workflow->canReview($user, $r))->values());
        }

        return view('reports.index', [
            'reports' => $reports,
            'tab' => $tab,
            // Reports are written for the unit a coordinator leads, not for every unit below it.
            'reportUnits' => $this->access->grants($user, 'reports.create')->map->orgUnit->filter()->unique('id')->values(),
        ]);
    }

    /** Starts a draft straight away so autosave has something to save into. */
    public function start(Request $request)
    {
        $data = $request->validate(['org_unit_id' => ['required', 'exists:org_units,id']]);
        $unit = OrgUnit::findOrFail($data['org_unit_id']);
        abort_unless($this->access->can($request->user(), 'reports.create', $unit), 403);

        $report = ActivityReport::create([
            'org_unit_id' => $unit->id,
            'activity_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('reports.edit', $report);
    }

    public function show(Request $request, ActivityReport $report)
    {
        $user = $request->user();
        abort_unless($this->access->can($user, 'reports.view', $report->orgUnit) || $report->created_by === $user->id, 403);
        abort_if($report->status === 'draft' && $report->created_by !== $user->id, 403);

        $report->load(['orgUnit.parent', 'creator', 'reviewer', 'events.user', 'media', 'participants']);

        return view('reports.show', [
            'report' => $report,
            'canEdit' => $this->workflow->canEdit($user, $report),
            'canReview' => $this->workflow->canReview($user, $report),
            'canComment' => $this->workflow->canComment($user, $report),
            'canPublish' => $this->workflow->canPublish($user, $report),
            'canArchive' => $this->workflow->canArchive($user, $report),
        ]);
    }

    public function edit(Request $request, ActivityReport $report)
    {
        abort_unless($this->workflow->canEdit($request->user(), $report), 403);
        $report->load(['media', 'participants', 'orgUnit']);

        return view('reports.edit', [
            'report' => $report,
            'members' => Member::approved()->placedWithin($report->orgUnit)->where('status', 'active')
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'other_names', 'member_no']),
        ]);
    }

    public function update(Request $request, ActivityReport $report)
    {
        abort_unless($this->workflow->canEdit($request->user(), $report), 403);
        $this->save($request, $report);

        if ($request->input('action') === 'submit') {
            $this->workflow->submit($report->fresh(), $request->user());

            return redirect()->route('reports.show', $report)->with('status', 'Report submitted for review. You will see the outcome here.');
        }

        return redirect()->route('reports.edit', $report)->with('status', 'Draft saved.');
    }

    /** Called quietly by the form every few seconds; returns JSON. */
    public function autosave(Request $request, ActivityReport $report)
    {
        abort_unless($this->workflow->canEdit($request->user(), $report), 403);
        $this->save($request, $report, partial: true);

        return response()->json(['saved_at' => now()->format('H:i')]);
    }

    public function submit(Request $request, ActivityReport $report)
    {
        abort_unless($this->workflow->canEdit($request->user(), $report), 403);
        $this->workflow->submit($report, $request->user());

        return redirect()->route('reports.show', $report)->with('status', 'Report submitted for review.');
    }

    public function review(Request $request, ActivityReport $report)
    {
        abort_unless($this->workflow->canReview($request->user(), $report), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['start', 'approve', 'reject'])],
            'note' => ['nullable', 'required_if:decision,reject', 'string', 'max:1000'],
        ], ['note.required_if' => 'Please tell the coordinator what needs correcting.']);

        match ($data['decision']) {
            'start' => $this->workflow->startReview($report, $request->user()),
            'approve' => $this->workflow->approve($report, $request->user(), $data['note'] ?? null),
            'reject' => $this->workflow->reject($report, $request->user(), $data['note']),
        };

        return back()->with('status', match ($data['decision']) {
            'start' => 'Marked as under review.',
            'approve' => 'Report approved. It now counts towards dashboards and participation.',
            'reject' => 'Report returned for correction.',
        });
    }

    public function comment(Request $request, ActivityReport $report)
    {
        abort_unless($this->workflow->canComment($request->user(), $report), 403);
        $data = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $this->workflow->comment($report, $request->user(), $data['comment']);

        return back()->with('status', 'Comment added.');
    }

    public function publish(Request $request, ActivityReport $report)
    {
        abort_unless($this->workflow->canPublish($request->user(), $report), 403);
        $this->workflow->publish($report, $request->user());

        return back()->with('status', 'Published as a public highlight.');
    }

    public function archive(Request $request, ActivityReport $report)
    {
        abort_unless($this->workflow->canArchive($request->user(), $report), 403);
        $this->workflow->archive($report, $request->user());

        return back()->with('status', 'Report archived.');
    }

    /** Only an unsubmitted draft can be deleted, and only by its author. */
    public function destroy(Request $request, ActivityReport $report)
    {
        abort_unless($report->status === 'draft' && $report->created_by === $request->user()->id, 403);
        foreach ($report->media as $media) {
            Storage::disk('local')->delete($media->path);
        }
        $report->delete();

        return redirect()->route('reports.index', ['tab' => 'mine'])->with('status', 'Draft deleted.');
    }

    protected function save(Request $request, ActivityReport $report, bool $partial = false): void
    {
        $data = $request->validate([
            'activity_type' => ['nullable', Rule::in(array_keys(ActivityReport::TYPES))],
            'title' => ['nullable', 'string', 'max:150'],
            'event_name' => ['nullable', 'string', 'max:150'],
            'activity_date' => ['nullable', 'date', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'performers_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'attendance_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'souls_won' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'outcome' => ['nullable', 'string', 'max:3000'],
            'impact' => ['nullable', 'string', 'max:3000'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'participants' => ['array'],
            'participants.*' => ['integer'],
        ]);

        $report->fill(collect($data)->except('participants')->all())->save();

        if ($request->has('participants_present') || $request->has('participants')) {
            $allowed = Member::placedWithin($report->orgUnit)->whereIn('id', $data['participants'] ?? [])->pluck('id');
            $report->participants()->sync($allowed->mapWithKeys(fn ($id) => [$id => ['role' => 'participant']])->all());
        }
    }
}

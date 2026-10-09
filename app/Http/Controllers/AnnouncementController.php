<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use App\Services\AuditLogger;
use App\Services\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** The one central announcement board; targets decide where each item appears. */
class AnnouncementController extends Controller
{
    public function __construct(protected Access $access, protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        return view('announcements.index', [
            'announcements' => Announcement::live()->visibleTo($request->user())
                ->with('targets.orgUnit', 'orgUnit')
                ->orderByDesc('is_pinned')->latest('published_at')
                ->paginate(15),
            'canCreate' => $this->access->can($request->user(), 'announcements.create'),
        ]);
    }

    public function show(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        $visible = $announcement->status === 'published'
            && Announcement::whereKey($announcement->id)->live()->visibleTo($user)->exists();
        abort_unless($visible || $this->canManage($user, $announcement), 404);

        return view('announcements.show', ['announcement' => $announcement->load('targets.orgUnit', 'author'), 'canManage' => $this->canManage($user, $announcement)]);
    }

    public function image(Request $request, Announcement $announcement)
    {
        abort_unless($announcement->image_path && Storage::disk('local')->exists($announcement->image_path), 404);
        $visible = Announcement::whereKey($announcement->id)->live()->visibleTo($request->user())->exists();
        abort_unless($visible || $this->canManage($request->user(), $announcement), 404);

        return response()->file(Storage::disk('local')->path($announcement->image_path), ['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function manage(Request $request)
    {
        $user = $request->user();
        abort_unless($this->access->can($user, 'announcements.create') || $this->access->can($user, 'announcements.publish'), 403);

        return view('announcements.manage', [
            'mine' => Announcement::where('author_id', $user->id)->with('targets.orgUnit')->latest()->limit(50)->get(),
            'queue' => $this->access->can($user, 'announcements.publish')
                ? Announcement::where('status', 'submitted')->where('author_id', '!=', $user->id)->with('targets.orgUnit', 'author')->oldest()->get()
                : collect(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($this->access->can($request->user(), 'announcements.create'), 403);

        return view('announcements.form', $this->formData($request->user(), new Announcement));
    }

    public function store(Request $request, ImageStore $images)
    {
        $user = $request->user();
        abort_unless($this->access->can($user, 'announcements.create'), 403);
        [$data, $targets] = $this->validated($request);

        $announcement = DB::transaction(function () use ($data, $targets, $user, $request, $images) {
            $announcement = Announcement::create(array_merge($data, ['author_id' => $user->id, 'status' => 'draft']));
            $announcement->targets()->createMany($targets);
            if ($request->hasFile('image')) {
                $announcement->forceFill(['image_path' => $images->storeImage($request->file('image'), 'announcements', 1400)])->save();
            }

            return $announcement;
        });

        return $this->finish($request, $announcement->load('targets.orgUnit'));
    }

    public function edit(Request $request, Announcement $announcement)
    {
        abort_unless($this->canEdit($request->user(), $announcement), 403);

        return view('announcements.form', $this->formData($request->user(), $announcement->load('targets')));
    }

    public function update(Request $request, Announcement $announcement, ImageStore $images)
    {
        abort_unless($this->canEdit($request->user(), $announcement), 403);
        [$data, $targets] = $this->validated($request);

        DB::transaction(function () use ($announcement, $data, $targets, $request, $images) {
            $announcement->fill($data)->save();
            $announcement->targets()->delete();
            $announcement->targets()->createMany($targets);
            if ($request->hasFile('image')) {
                $announcement->forceFill(['image_path' => $images->storeImage($request->file('image'), 'announcements', 1400)])->save();
            }
        });

        return $this->finish($request, $announcement->fresh('targets.orgUnit'));
    }

    public function decide(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        abort_unless($announcement->status === 'submitted' && $announcement->author_id !== $user->id && $this->access->can($user, 'announcements.publish'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:250'],
        ]);

        if ($data['decision'] === 'approve') {
            $announcement->forceFill(['status' => 'published', 'published_at' => now(), 'reviewed_by' => $user->id, 'reject_reason' => null])->save();
            $this->audit->log('announcement.published', $announcement, 'Approved and published: '.$announcement->title);
        } else {
            $announcement->forceFill(['status' => 'rejected', 'reviewed_by' => $user->id, 'reject_reason' => $data['reason']])->save();
            $this->audit->log('announcement.rejected', $announcement, 'Returned: '.$announcement->title, ['reason' => $data['reason']]);
        }

        return back()->with('status', $data['decision'] === 'approve' ? 'Announcement published.' : 'Announcement returned to its author.');
    }

    public function archive(Request $request, Announcement $announcement)
    {
        abort_unless($this->canManage($request->user(), $announcement), 403);
        $announcement->forceFill(['status' => 'archived', 'is_pinned' => false])->save();
        $this->audit->log('announcement.archived', $announcement, 'Archived: '.$announcement->title);

        return redirect()->route('announcements.manage')->with('status', 'Announcement archived.');
    }

    /** Saves as draft, sends for approval, or publishes, depending on audience and rights. */
    protected function finish(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        if ($request->input('action') === 'draft') {
            $announcement->forceFill(['status' => 'draft'])->save();

            return redirect()->route('announcements.edit', $announcement)->with('status', 'Draft saved.');
        }

        if ($this->needsApproval($user, $announcement)) {
            $announcement->forceFill(['status' => 'submitted'])->save();
            $this->audit->log('announcement.submitted', $announcement, 'Sent for approval: '.$announcement->title);

            return redirect()->route('announcements.manage')->with('status', 'Sent for approval. Public, national and role-wide announcements are checked before they go out.');
        }

        $announcement->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $this->audit->log('announcement.published', $announcement, 'Published: '.$announcement->title);

        return redirect()->route('announcements.show', $announcement)->with('status', 'Announcement published.');
    }

    protected function needsApproval(User $user, Announcement $announcement): bool
    {
        if ($this->access->can($user, 'announcements.publish')) {
            return false;
        }

        return $announcement->targets->contains(fn ($t) => $t->audience !== 'org_unit' || $t->orgUnit?->type === OrgUnit::NATIONAL);
    }

    protected function canEdit(?User $user, Announcement $announcement): bool
    {
        return $user && $announcement->author_id === $user->id && in_array($announcement->status, ['draft', 'rejected', 'submitted']);
    }

    protected function canManage(?User $user, Announcement $announcement): bool
    {
        return $user && ($announcement->author_id === $user->id || $this->access->can($user, 'announcements.publish'));
    }

    protected function formData(User $user, Announcement $announcement): array
    {
        return [
            'announcement' => $announcement,
            'units' => $this->access->unitsWithin($user, 'announcements.create')->where('type', '!=', OrgUnit::ASSEMBLY)
                ->merge($this->access->unitsWithin($user, 'announcements.create', OrgUnit::ASSEMBLY)->take(200)),
            'roles' => Role::orderBy('sort')->get(),
            'canPublish' => $this->access->can($user, 'announcements.publish'),
        ];
    }

    protected function validated(Request $request): array
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'cta_label' => ['nullable', 'required_with:link_url', 'string', 'max:60'],
            'is_mandatory' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'image' => ['nullable', 'image', 'max:'.config('godram.uploads.image_max_kb')],
            'audience_public' => ['nullable', 'boolean'],
            'audience_units' => ['array'],
            'audience_units.*' => ['integer', 'exists:org_units,id'],
            'audience_roles' => ['array'],
            'audience_roles.*' => ['string', 'exists:roles,key'],
        ], ['cta_label.required_with' => 'Add a short button label for the link, such as "Register now".']);

        $targets = [];
        if ($request->boolean('audience_public')) {
            $targets[] = ['audience' => 'public'];
        }
        foreach ($data['audience_units'] ?? [] as $unitId) {
            $unit = OrgUnit::findOrFail($unitId);
            abort_unless($this->access->can($user, 'announcements.create', $unit), 403);
            $targets[] = ['audience' => 'org_unit', 'org_unit_id' => $unit->id];
        }
        foreach ($data['audience_roles'] ?? [] as $roleKey) {
            $targets[] = ['audience' => 'role', 'role_key' => $roleKey];
        }
        if (! $targets) {
            back()->withInput()->withErrors(['audience' => 'Choose who should see this announcement.'])->throwResponse();
        }

        $fields = collect($data)->only(['title', 'body', 'link_url', 'cta_label', 'expires_at'])->all();
        $fields['is_mandatory'] = $request->boolean('is_mandatory');
        $fields['is_pinned'] = $request->boolean('is_pinned') && $this->access->can($user, 'announcements.publish');
        $fields['org_unit_id'] = $this->access->units($user, 'announcements.create')->first()?->id;

        return [$fields, $targets];
    }
}

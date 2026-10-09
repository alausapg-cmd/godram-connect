<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Skill;
use App\Models\User;
use App\Services\Access;
use App\Services\AuditLogger;
use App\Services\DuplicateFinder;
use App\Services\MembershipService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function __construct(
        protected Access $access,
        protected MembershipService $membership,
        protected AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $paths = $this->access->paths($user, 'members.view');
        abort_if(empty($paths), 403);

        $units = $this->access->unitsWithin($user, 'members.view');
        $unit = $request->filled('unit') ? $units->firstWhere('id', (int) $request->input('unit')) : null;

        $members = Member::query()
            ->approved()
            ->when($unit, fn ($q) => $q->placedWithin($unit), fn ($q) => $q->placedWithinPaths($paths))
            ->search($request->input('q'))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'archived'))
            ->when($request->input('skill'), fn ($q, $s) => $q->whereHas('skills', fn ($q) => $q->where('skills.id', $s)))
            ->when($request->input('role'), fn ($q, $r) => $q->whereHas('activeRoleAssignments.role', fn ($q) => $q->where('key', $r)))
            ->with(['currentPlacement.orgUnit.parent', 'skills'])
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return view('members.index', [
            'members' => $members,
            'units' => $units,
            'unit' => $unit,
            'skills' => Skill::orderBy('sort')->get(),
            'canCreate' => $this->access->can($user, 'members.create'),
            'canExport' => $this->access->can($user, 'members.export'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $paths = $this->access->paths($user, 'members.export');
        abort_if(empty($paths), 403);

        $this->audit->log('members.exported', null, 'Exported a member list', ['filters' => $request->only('q', 'status', 'skill')]);

        $query = Member::approved()->placedWithinPaths($paths)->search($request->input('q'))
            ->with(['currentPlacement.orgUnit', 'skills'])->orderBy('last_name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Member ID', 'First name', 'Last name', 'Phone', 'Email', 'Assembly', 'District', 'Region', 'Status', 'Skills', 'Joined']);
            $query->chunk(500, function ($members) use ($out) {
                foreach ($members as $m) {
                    $assembly = $m->currentPlacement?->orgUnit;
                    fputcsv($out, [
                        $m->member_no, $m->first_name, $m->last_name, Phone::display($m->phone), $m->email,
                        $assembly?->name, $assembly?->ancestorOfType('district')?->name, $assembly?->ancestorOfType('region')?->name,
                        $m->statusLabel(), $m->skills->pluck('name')->implode('; '), $m->joined_on?->toDateString(),
                    ]);
                }
            });
            fclose($out);
        }, 'godram-members-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request)
    {
        $assemblies = $this->access->unitsWithin($request->user(), 'members.create', OrgUnit::ASSEMBLY);
        abort_if($assemblies->isEmpty(), 403);

        return view('members.form', [
            'member' => new Member(['joined_on' => now()]),
            'assemblies' => $assemblies,
            'skills' => Skill::orderBy('sort')->get(),
            'duplicates' => collect(),
        ]);
    }

    public function store(Request $request, DuplicateFinder $finder)
    {
        $data = $this->validated($request);
        $assembly = OrgUnit::findOrFail($data['org_unit_id']);
        abort_unless($this->access->can($request->user(), 'members.create', $assembly), 403);

        // Similar names in the same District: show them and ask for confirmation.
        $similar = $finder->similarNames($data['first_name'], $data['last_name'], $assembly);
        if ($similar->isNotEmpty() && ! $request->boolean('confirm_not_duplicate')) {
            return back()->withInput()->with('duplicates', $similar->load('currentPlacement.orgUnit')->all());
        }

        $member = $this->membership->register(
            collect($data)->except(['org_unit_id', 'confirm_not_duplicate'])->all(),
            $assembly,
            $request->user(),
        );

        return redirect()->route('members.show', $member)->with('status', $member->full_name.' has been registered as '.$member->member_no.'.');
    }

    public function show(Request $request, Member $member)
    {
        $user = $request->user();
        $assembly = $member->assembly();
        abort_unless($user->member_id === $member->id || ($assembly && $this->access->can($user, 'members.view', $assembly)), 403);

        $member->load(['placements.orgUnit', 'skills', 'roleAssignments.role', 'roleAssignments.orgUnit', 'statusChanges.changedBy', 'transferRequests.toUnit', 'user']);

        return view('members.show', [
            'member' => $member,
            'assembly' => $assembly,
            'reports' => $member->reports()->counted()->with('orgUnit')->latest('activity_date')->limit(10)->get(),
            'canEdit' => $assembly && $this->access->can($user, 'members.update', $assembly),
            'canTransfer' => $assembly && $this->access->can($user, 'members.transfer.request', $assembly),
        ]);
    }

    public function edit(Request $request, Member $member)
    {
        $this->authorizeUpdate($request, $member);

        return view('members.form', [
            'member' => $member,
            'assemblies' => collect([$member->assembly()]),
            'skills' => Skill::orderBy('sort')->get(),
            'duplicates' => collect(),
        ]);
    }

    public function update(Request $request, Member $member)
    {
        $this->authorizeUpdate($request, $member);
        $data = $this->validated($request, $member);
        $skills = $data['skills'] ?? [];
        $before = $member->only(['first_name', 'last_name', 'other_names', 'phone', 'email', 'gender']);

        $member->fill(collect($data)->except(['org_unit_id', 'skills', 'confirm_not_duplicate', 'joined_on'])->all())->save();
        $member->skills()->sync($skills);
        $member->user?->forceFill(['name' => $member->full_name, 'phone' => $member->phone, 'email' => $member->email])->save();

        $changes = collect($member->only(array_keys($before)))->diffAssoc($before)->keys()->all();
        $this->audit->log('member.updated', $member, 'Updated '.$member->full_name, ['fields' => $changes], $member->assembly());

        return redirect()->route('members.show', $member)->with('status', 'Changes saved.');
    }

    public function status(Request $request, Member $member)
    {
        $this->authorizeUpdate($request, $member);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Member::STATUSES))],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $this->membership->changeStatus($member, $data['status'], $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Status updated to '.$member->statusLabel().'.');
    }

    /** For members who sign in by phone and have no email to reset with. */
    public function resetPassword(Request $request, Member $member)
    {
        $this->authorizeUpdate($request, $member);
        abort_unless($member->user, 404);
        $temporary = $this->temporaryPassword();
        $member->user->forceFill(['password' => $temporary, 'must_change_password' => true])->save();
        $this->audit->log('member.password_reset', $member, 'Temporary password issued for '.$member->full_name, [], $member->assembly());

        return back()->with('temporary_password', $temporary);
    }

    public function createAccount(Request $request, Member $member)
    {
        $this->authorizeUpdate($request, $member);
        abort_if($member->user, 422);
        abort_unless($member->phone || $member->email, 422, 'Add a phone number or email first.');
        $temporary = $this->temporaryPassword();
        User::create([
            'name' => $member->full_name, 'email' => $member->email, 'phone' => $member->phone,
            'password' => $temporary, 'member_id' => $member->id, 'must_change_password' => true,
        ]);
        $this->audit->log('user.created', $member, 'Sign-in account created for '.$member->full_name, [], $member->assembly());

        return back()->with('temporary_password', $temporary);
    }

    protected function authorizeUpdate(Request $request, Member $member): void
    {
        $assembly = $member->assembly();
        abort_unless($assembly && $this->access->can($request->user(), 'members.update', $assembly), 403);
    }

    protected function temporaryPassword(): string
    {
        return Str::upper(Str::random(4)).'-'.random_int(1000, 9999);
    }

    protected function validated(Request $request, ?Member $member = null): array
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'email' => $request->filled('email') ? mb_strtolower(trim($request->input('email'))) : null,
        ]);

        return $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'other_names' => ['nullable', 'string', 'max:60'],
            'gender' => ['nullable', Rule::in(array_keys(Member::GENDERS))],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('members', 'phone')->ignore($member?->id)],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('members', 'email')->ignore($member?->id)],
            'joined_on' => ['nullable', 'date', 'before_or_equal:today'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'skills' => ['array'],
            'skills.*' => ['integer', 'exists:skills,id'],
            'org_unit_id' => [$member ? 'nullable' : 'required', Rule::exists('org_units', 'id')->where('type', OrgUnit::ASSEMBLY)],
            'confirm_not_duplicate' => ['nullable', 'boolean'],
        ], [
            'phone.unique' => 'Another member already uses this phone number. They may already be registered: search for them before adding again.',
            'email.unique' => 'Another member already uses this email address.',
        ]);
    }
}

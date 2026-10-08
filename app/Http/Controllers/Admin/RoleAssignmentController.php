<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Services\Access;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleAssignmentController extends Controller
{
    public function __construct(protected Access $access, protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        abort_unless($this->access->can($request->user(), 'roles.manage'), 403);

        return view('admin.roles', [
            'assignments' => RoleAssignment::active()->with(['member', 'role', 'orgUnit'])
                ->join('roles', 'roles.id', '=', 'role_assignments.role_id')
                ->orderBy('roles.sort')->select('role_assignments.*')->paginate(40),
            'roles' => Role::orderBy('sort')->get(),
            'units' => OrgUnit::orderBy('path')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'member_no' => ['required', 'string', 'exists:members,member_no'],
            'role_id' => ['required', 'exists:roles,id'],
            'org_unit_id' => ['required', 'exists:org_units,id'],
        ], ['member_no.exists' => 'No member has that Member ID.']);

        $unit = OrgUnit::findOrFail($data['org_unit_id']);
        $role = Role::findOrFail($data['role_id']);
        abort_unless($this->access->can($user, 'roles.manage', $unit), 403);

        if ($role->scope_type && $role->scope_type !== $unit->type) {
            return back()->withErrors(['org_unit_id' => 'A '.$role->name.' is appointed to a '.ucfirst($role->scope_type).'.'])->withInput();
        }

        $member = Member::where('member_no', $data['member_no'])->firstOrFail();
        $assignment = RoleAssignment::create([
            'member_id' => $member->id, 'role_id' => $role->id, 'org_unit_id' => $unit->id,
            'starts_on' => now()->toDateString(), 'assigned_by' => $user->id,
        ]);
        $this->audit->log('role.assigned', $member, $member->full_name.' appointed '.$assignment->load('role', 'orgUnit')->title(), ['role' => $role->key], $unit);

        $note = $member->user ? '' : ' They do not have a sign-in account yet: create one from their profile.';

        return back()->with('status', $member->full_name.' is now '.$assignment->title().'.'.$note);
    }

    public function end(Request $request, RoleAssignment $assignment)
    {
        abort_unless($this->access->can($request->user(), 'roles.manage', $assignment->orgUnit), 403);
        abort_if($assignment->member_id === $request->user()->member_id, 422, 'You cannot end your own role.');
        $assignment->forceFill(['ends_on' => now()->subDay()->toDateString()])->save();
        $this->audit->log('role.ended', $assignment->member, 'Ended '.$assignment->title().' for '.$assignment->member->full_name, ['role' => $assignment->role->key], $assignment->orgUnit);

        return back()->with('status', 'Role ended.');
    }
}

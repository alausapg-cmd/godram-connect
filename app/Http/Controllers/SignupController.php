<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\Access;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class SignupController extends Controller
{
    public function __construct(protected Access $access, protected MembershipService $membership) {}

    public function index(Request $request)
    {
        $paths = $this->access->paths($request->user(), 'members.approve_signup');
        abort_if(empty($paths), 403);

        return view('members.signups', [
            'members' => Member::pending()->where('status', '!=', 'archived')->placedWithinPaths($paths)
                ->with('currentPlacement.orgUnit.parent')->oldest()->get(),
        ]);
    }

    public function approve(Request $request, Member $member)
    {
        $this->authorizeFor($request, $member);
        $this->membership->approveSignup($member, $request->user());

        return back()->with('status', $member->full_name.' is now confirmed as '.$member->member_no.'.');
    }

    public function reject(Request $request, Member $member)
    {
        $this->authorizeFor($request, $member);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:200']])['reason'] ?? null;
        $this->membership->rejectSignup($member, $request->user(), $reason);

        return back()->with('status', 'Sign-up declined.');
    }

    protected function authorizeFor(Request $request, Member $member): void
    {
        abort_unless($member->isPending() && $member->assembly() && $this->access->can($request->user(), 'members.approve_signup', $member->assembly()), 403);
    }
}

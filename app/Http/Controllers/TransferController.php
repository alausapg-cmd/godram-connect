<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\TransferRequest;
use App\Services\Access;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransferController extends Controller
{
    public function __construct(protected Access $access, protected MembershipService $membership) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $approvePaths = $this->access->paths($user, 'members.transfer.approve');
        $requestPaths = $this->access->paths($user, 'members.transfer.request');
        abort_if(empty($approvePaths) && empty($requestPaths), 403);

        $inScope = function ($q, $column, $paths) {
            $q->whereHas($column, function ($q) use ($paths) {
                $q->where(function ($q) use ($paths) {
                    foreach ($paths as $p) {
                        $q->orWhere('path', 'like', $p.'%');
                    }
                });
            });
        };

        $incoming = TransferRequest::where('status', 'pending')
            ->when($approvePaths, fn ($q) => $inScope($q, 'toUnit', $approvePaths), fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['member', 'fromUnit.parent', 'toUnit.parent', 'requester'])->oldest()->get()
            ->map(fn ($t) => tap($t, fn ($t) => $t->can_decide = $this->membership->canDecideTransfer($user, $t)));

        $outgoing = TransferRequest::query()
            ->when($requestPaths, fn ($q) => $inScope($q, 'fromUnit', $requestPaths), fn ($q) => $q->whereRaw('1 = 0'))
            ->with(['member', 'fromUnit', 'toUnit.parent', 'decider'])->latest()->limit(30)->get();

        return view('transfers.index', compact('incoming', 'outgoing'));
    }

    public function create(Request $request, Member $member)
    {
        $from = $member->assembly();
        abort_unless($from && $this->access->can($request->user(), 'members.transfer.request', $from), 403);

        return view('transfers.create', [
            'member' => $member,
            'from' => $from,
            'districts' => OrgUnit::ofType(OrgUnit::DISTRICT)->orderBy('name')->with('children', 'parent')->get(),
        ]);
    }

    public function store(Request $request, Member $member)
    {
        $from = $member->assembly();
        abort_unless($from && $this->access->can($request->user(), 'members.transfer.request', $from), 403);
        $data = $request->validate([
            'to_unit_id' => ['required', Rule::exists('org_units', 'id')->where('type', OrgUnit::ASSEMBLY)],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $to = OrgUnit::findOrFail($data['to_unit_id']);
        $this->membership->requestTransfer($member, $to, $request->user(), $data['reason'] ?? null);

        return redirect()->route('members.show', $member)->with('status', 'Transfer requested. '.$to->fullName().' will be asked to accept it.');
    }

    public function decide(Request $request, TransferRequest $transfer)
    {
        abort_unless($this->membership->canDecideTransfer($request->user(), $transfer), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $approve = $data['decision'] === 'approve';
        $this->membership->decideTransfer($transfer, $request->user(), $approve, $data['note'] ?? null);

        return back()->with('status', $approve
            ? $transfer->member->full_name.' now belongs to '.$transfer->toUnit->fullName().'.'
            : 'Transfer declined.');
    }
}

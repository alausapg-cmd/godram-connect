<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberPlacement;
use App\Models\MemberStatusChange;
use App\Models\OrgUnit;
use App\Models\TransferRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    public function __construct(
        protected AuditLogger $audit,
        protected Access $access,
    ) {}

    /**
     * Registers one canonical member and places them in an Assembly. The
     * member then shows up in every District, Region and National view
     * through the tree, with no copies made.
     */
    public function register(array $data, OrgUnit $assembly, ?User $by, bool $approved = true, ?string $password = null): Member
    {
        if ($assembly->type !== OrgUnit::ASSEMBLY) {
            throw ValidationException::withMessages(['org_unit_id' => 'Members are placed in an Assembly.']);
        }

        return DB::transaction(function () use ($data, $assembly, $by, $approved, $password) {
            $skills = $data['skills'] ?? [];
            unset($data['skills']);

            $member = Member::create(array_merge($data, [
                'joined_on' => $data['joined_on'] ?? now()->toDateString(),
                'approved_at' => $approved ? now() : null,
                'approved_by' => $approved ? $by?->id : null,
                'created_by' => $by?->id,
            ]));

            MemberPlacement::create([
                'member_id' => $member->id,
                'org_unit_id' => $assembly->id,
                'starts_on' => $member->joined_on ?? now(),
                'reason' => 'Registered',
                'created_by' => $by?->id,
            ]);

            if ($skills) {
                $member->skills()->sync($skills);
            }

            if ($password !== null) {
                User::create([
                    'name' => $member->full_name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'password' => $password,
                    'member_id' => $member->id,
                ]);
            }

            $this->audit->log(
                $approved ? 'member.created' : 'member.signed_up',
                $member,
                ($approved ? 'Registered ' : 'Self sign-up by ').$member->full_name.' in '.$assembly->fullName(),
                ['member_no' => $member->member_no],
                $assembly,
                $by?->id,
            );

            return $member;
        });
    }

    public function approveSignup(Member $member, User $by): void
    {
        $member->forceFill(['approved_at' => now(), 'approved_by' => $by->id])->save();
        $this->audit->log('member.signup_confirmed', $member, 'Confirmed sign-up of '.$member->full_name, [], $member->assembly());
    }

    public function rejectSignup(Member $member, User $by, ?string $reason = null): void
    {
        DB::transaction(function () use ($member, $by, $reason) {
            $this->audit->log('member.signup_rejected', $member, 'Declined sign-up of '.$member->full_name, ['reason' => $reason], $member->assembly());
            $member->user?->forceFill(['is_active' => false])->save();
            $this->changeStatus($member, 'archived', $by, 'Sign-up declined'.($reason ? ': '.$reason : ''));
        });
    }

    public function changeStatus(Member $member, string $status, ?User $by, ?string $note = null): void
    {
        if ($member->status === $status) {
            return;
        }
        MemberStatusChange::create([
            'member_id' => $member->id,
            'from_status' => $member->status,
            'to_status' => $status,
            'note' => $note,
            'changed_by' => $by?->id,
        ]);
        $this->audit->log('member.status_changed', $member, $member->full_name.': '.$member->statusLabel().' to '.(Member::STATUSES[$status] ?? $status), ['note' => $note], $member->assembly());
        $member->forceFill(['status' => $status])->save();
    }

    public function requestTransfer(Member $member, OrgUnit $to, User $by, ?string $reason = null): TransferRequest
    {
        $from = $member->assembly();
        if (! $from) {
            throw ValidationException::withMessages(['to_unit_id' => 'This member has no current Assembly.']);
        }
        if ($to->type !== OrgUnit::ASSEMBLY || $to->id === $from->id) {
            throw ValidationException::withMessages(['to_unit_id' => 'Choose a different Assembly.']);
        }
        if (TransferRequest::where('member_id', $member->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['to_unit_id' => 'This member already has a transfer waiting for approval.']);
        }

        $request = TransferRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $from->id,
            'to_unit_id' => $to->id,
            'reason' => $reason,
            'requested_by' => $by->id,
        ]);

        $this->audit->log('member.transfer_requested', $member, 'Transfer of '.$member->full_name.' from '.$from->fullName().' to '.$to->fullName().' requested', ['transfer_id' => $request->id], $from);

        return $request;
    }

    /**
     * The receiving side decides. Within one District the receiving
     * Assembly Coordinator can accept; across Districts it needs a
     * District Coordinator (or higher) over the receiving Assembly.
     */
    public function canDecideTransfer(?User $user, TransferRequest $transfer): bool
    {
        if (! $user || $transfer->status !== 'pending' || $transfer->requested_by === $user->id) {
            return false;
        }
        $to = $transfer->toUnit;
        if (! $this->access->can($user, 'members.transfer.approve', $to)) {
            return false;
        }
        if ($transfer->crossesDistricts()) {
            $districtDepth = $to->ancestorOfType(OrgUnit::DISTRICT)?->depth ?? 2;

            return $this->access->bestDepthOver($user, 'members.transfer.approve', $to) <= $districtDepth;
        }

        return true;
    }

    public function decideTransfer(TransferRequest $transfer, User $by, bool $approve, ?string $note = null): void
    {
        DB::transaction(function () use ($transfer, $by, $approve, $note) {
            $transfer->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'decided_by' => $by->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ])->save();

            $member = $transfer->member;

            if ($approve) {
                // Close the old placement and open the new one: same member, new place.
                MemberPlacement::where('member_id', $member->id)->whereNull('ends_on')
                    ->update(['ends_on' => now()->toDateString(), 'updated_at' => now()]);
                MemberPlacement::create([
                    'member_id' => $member->id,
                    'org_unit_id' => $transfer->to_unit_id,
                    'starts_on' => now()->toDateString(),
                    'reason' => 'Transfer from '.$transfer->fromUnit->fullName(),
                    'created_by' => $by->id,
                ]);
            }

            $this->audit->log(
                $approve ? 'member.transferred' : 'member.transfer_rejected',
                $member,
                ($approve ? 'Transferred ' : 'Declined transfer of ').$member->full_name.' to '.$transfer->toUnit->fullName(),
                ['transfer_id' => $transfer->id, 'note' => $note],
                $transfer->toUnit,
            );
        });
    }
}

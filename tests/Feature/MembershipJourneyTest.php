<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\TransferRequest;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

/** Acceptance scenarios 1 to 4 from the master prompt. */
class MembershipJourneyTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    protected function registerChioma(): Member
    {
        $this->actingAs($this->users['agege'])
            ->post(route('members.store'), [
                'first_name' => 'Chioma',
                'last_name' => 'Nwosu',
                'gender' => 'female',
                'phone' => '0803 555 0101',
                'org_unit_id' => $this->agege->id,
            ])->assertRedirect();

        return Member::where('first_name', 'Chioma')->firstOrFail();
    }

    public function test_scenario_1_assembly_coordinator_registers_a_new_member(): void
    {
        $member = $this->registerChioma();

        $this->assertMatchesRegularExpression('/^GDM-\d{6}$/', $member->member_no);
        $this->assertSame('+2348035550101', $member->phone);
        $this->assertNotNull($member->approved_at);
        $this->assertSame($this->agege->id, $member->assembly()->id);
        $this->assertTrue(AuditLog::where('action', 'member.created')->where('subject_id', $member->id)->exists());
    }

    public function test_assembly_coordinator_cannot_register_into_another_assembly(): void
    {
        $this->actingAs($this->users['agege'])
            ->post(route('members.store'), ['first_name' => 'Ola', 'last_name' => 'Bello', 'org_unit_id' => $this->mokola->id])
            ->assertForbidden();
    }

    public function test_scenario_2_new_member_appears_in_district_and_region_views(): void
    {
        $member = $this->registerChioma();

        foreach (['lagos', 'region', 'national'] as $who) {
            $this->actingAs($this->users[$who])->get(route('members.index'))->assertOk()->assertSee('Chioma Nwosu');
            $this->actingAs($this->users[$who])->get(route('members.show', $member))->assertOk();
        }

        // Another District and another Assembly do not see the member.
        $this->actingAs($this->users['ibadan'])->get(route('members.index'))->assertOk()->assertDontSee('Chioma Nwosu');
        $this->actingAs($this->users['ibadan'])->get(route('members.show', $member))->assertForbidden();
        $this->actingAs($this->users['mushin'])->get(route('members.show', $member))->assertForbidden();
    }

    public function test_scenario_3_and_4_member_transfers_across_districts(): void
    {
        $member = $this->registerChioma();

        // Agege asks to move the member to Mokola, in another District.
        $this->actingAs($this->users['agege'])
            ->post(route('transfers.store', $member), ['to_unit_id' => $this->mokola->id, 'reason' => 'Relocated to Ibadan'])
            ->assertRedirect();
        $transfer = TransferRequest::firstOrFail();
        $this->assertSame('pending', $transfer->status);

        // The sender cannot accept its own request, and an Assembly cannot accept a cross-District move.
        $this->actingAs($this->users['agege'])->post(route('transfers.decide', $transfer), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->users['mokola'])->post(route('transfers.decide', $transfer), ['decision' => 'approve'])->assertForbidden();

        // The receiving District accepts.
        $this->actingAs($this->users['ibadan'])->post(route('transfers.decide', $transfer), ['decision' => 'approve'])->assertRedirect();

        $member->refresh();
        $this->assertSame($this->mokola->id, $member->assembly()->id);
        $this->assertSame(2, $member->placements()->count(), 'The old placement is kept as history.');
        $this->assertNotNull($member->placements()->where('org_unit_id', $this->agege->id)->value('ends_on'));

        // Scenario 4: the Districts see the updated membership.
        $this->actingAs($this->users['ibadan'])->get(route('members.index'))->assertSee('Chioma Nwosu');
        $this->actingAs($this->users['lagos'])->get(route('members.index'))->assertDontSee('Chioma Nwosu');
        $this->actingAs($this->users['mokola'])->get(route('members.show', $member))->assertOk();
        $this->actingAs($this->users['agege'])->get(route('members.show', $member))->assertForbidden();
        $this->assertSame(1, Member::placedWithin($this->ibadan)->where('first_name', 'Chioma')->count());
    }

    public function test_transfer_within_a_district_can_be_accepted_by_the_receiving_assembly(): void
    {
        $member = $this->registerChioma();
        $this->actingAs($this->users['agege'])->post(route('transfers.store', $member), ['to_unit_id' => $this->mushin->id]);

        $this->actingAs($this->users['mushin'])
            ->post(route('transfers.decide', TransferRequest::firstOrFail()), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertSame($this->mushin->id, $member->fresh()->assembly()->id);
    }

    public function test_same_phone_number_is_refused_and_similar_names_are_flagged(): void
    {
        $this->registerChioma();

        $this->actingAs($this->users['agege'])
            ->post(route('members.store'), ['first_name' => 'Grace', 'last_name' => 'Obi', 'phone' => '+2348035550101', 'org_unit_id' => $this->agege->id])
            ->assertSessionHasErrors('phone');

        // A near-identical name in the same District asks for confirmation first.
        $this->actingAs($this->users['mushin'])
            ->post(route('members.store'), ['first_name' => 'Chiomah', 'last_name' => 'Nwosu', 'org_unit_id' => $this->mushin->id])
            ->assertSessionHas('duplicates');
        $this->assertSame(1, Member::where('last_name', 'Nwosu')->count());

        $this->actingAs($this->users['mushin'])
            ->post(route('members.store'), ['first_name' => 'Chiomah', 'last_name' => 'Nwosu', 'org_unit_id' => $this->mushin->id, 'confirm_not_duplicate' => 1])
            ->assertRedirect();
        $this->assertSame(2, Member::where('last_name', 'Nwosu')->count());
    }

    public function test_self_signup_waits_for_assembly_approval(): void
    {
        $this->post(route('register'), [
            'first_name' => 'Yemi',
            'last_name' => 'Ade',
            'phone' => '08099990000',
            'org_unit_id' => $this->mushin->id,
            'password' => 'a-strong-pass-9',
            'password_confirmation' => 'a-strong-pass-9',
        ])->assertRedirect();

        $member = Member::where('first_name', 'Yemi')->firstOrFail();
        $this->assertTrue($member->isPending());
        $this->actingAs($this->users['agege'])->post(route('signups.approve', $member))->assertForbidden();
        $this->actingAs($this->users['mushin'])->post(route('signups.approve', $member))->assertRedirect();
        $this->assertFalse($member->fresh()->isPending());
    }
}

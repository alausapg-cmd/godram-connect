<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class AccessAndAuditTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    public function test_sign_in_with_a_local_phone_number(): void
    {
        $user = $this->users['agege'];
        $local = '0'.substr($user->phone, 4);

        $this->post(route('login'), ['identifier' => $local, 'password' => 'secret-pass-1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(AuditLog::where('action', 'auth.login')->exists());
    }

    public function test_wrong_password_is_refused(): void
    {
        $this->post(route('login'), ['identifier' => $this->users['agege']->email, 'password' => 'nope'])->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_deactivated_account_is_signed_out(): void
    {
        $user = $this->users['agege'];
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_temporary_password_must_be_changed(): void
    {
        $user = $this->users['agege'];
        $user->update(['must_change_password' => true]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
    }

    public function test_every_dashboard_loads_for_its_role(): void
    {
        foreach ($this->users as $who => $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk();
        }
        $plain = $this->person($this->agege, 'Plain', 'Member', password: 'secret-pass-1')->user;
        $this->actingAs($plain)->get(route('dashboard'))->assertOk();
        $this->actingAs($plain)->get(route('members.index'))->assertForbidden();
        $this->actingAs($plain)->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_only_administrators_manage_structure_and_roles(): void
    {
        $this->actingAs($this->users['lagos'])->get(route('admin.units.index'))->assertForbidden();
        $this->actingAs($this->users['admin'])->get(route('admin.units.index'))->assertOk();
        $this->actingAs($this->users['national'])->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($this->users['national'])->get(route('admin.audit.index'))->assertOk();
    }

    public function test_audit_chain_detects_tampering(): void
    {
        $this->artisan('audit:verify')->assertSuccessful();

        $entry = AuditLog::orderBy('id')->skip(2)->firstOrFail();
        AuditLog::whereKey($entry->id)->update(['summary' => 'Something else']);

        $this->assertSame($entry->id, app(AuditLogger::class)->firstBrokenEntry());
        $this->artisan('audit:verify')->assertFailed();
    }

    public function test_member_export_is_scoped_and_audited(): void
    {
        $this->person($this->mokola, 'Ibadan', 'Only');
        $csv = $this->actingAs($this->users['lagos'])->get(route('members.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Lagos', $csv);
        $this->assertStringNotContainsString('Only', $csv);
        $this->assertTrue(AuditLog::where('action', 'members.exported')->exists());
    }
}

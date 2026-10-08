<?php

namespace Tests\Concerns;

use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\MembershipService;
use Database\Seeders\AccessSeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * A small ministry: one Region with two Districts.
 *   Lagos District: Agege Assembly, Mushin Assembly
 *   Ibadan District: Mokola Assembly
 * Each unit has a coordinator with a login.
 */
trait BuildsMinistry
{
    use RefreshDatabase;

    protected OrgUnit $national;
    protected OrgUnit $region;
    protected OrgUnit $lagos;
    protected OrgUnit $ibadan;
    protected OrgUnit $agege;
    protected OrgUnit $mushin;
    protected OrgUnit $mokola;

    /** @var array<string, User> */
    protected array $users = [];

    protected int $phoneSeq = 8030000000;

    protected function buildMinistry(): void
    {
        $this->seed([AccessSeeder::class, SkillSeeder::class]);

        $this->national = OrgUnit::create(['type' => OrgUnit::NATIONAL, 'name' => 'GODRAM National']);
        $this->region = OrgUnit::create(['type' => OrgUnit::REGION, 'name' => 'Region 1', 'parent_id' => $this->national->id]);
        $this->lagos = OrgUnit::create(['type' => OrgUnit::DISTRICT, 'name' => 'Lagos', 'parent_id' => $this->region->id]);
        $this->ibadan = OrgUnit::create(['type' => OrgUnit::DISTRICT, 'name' => 'Ibadan', 'parent_id' => $this->region->id]);
        $this->agege = OrgUnit::create(['type' => OrgUnit::ASSEMBLY, 'name' => 'Agege', 'parent_id' => $this->lagos->id]);
        $this->mushin = OrgUnit::create(['type' => OrgUnit::ASSEMBLY, 'name' => 'Mushin', 'parent_id' => $this->lagos->id]);
        $this->mokola = OrgUnit::create(['type' => OrgUnit::ASSEMBLY, 'name' => 'Mokola', 'parent_id' => $this->ibadan->id]);

        $this->users = [
            'national' => $this->leader('national_coordinator', $this->national, $this->agege, 'Paul', 'Alausa'),
            'admin' => $this->leader('system_administrator', $this->national, $this->agege, 'Sade', 'Admin'),
            'region' => $this->leader('regional_coordinator', $this->region, $this->agege, 'Tunde', 'Region'),
            'lagos' => $this->leader('district_coordinator', $this->lagos, $this->mushin, 'Kemi', 'Lagos'),
            'ibadan' => $this->leader('district_coordinator', $this->ibadan, $this->mokola, 'Bayo', 'Ibadan'),
            'agege' => $this->leader('assembly_coordinator', $this->agege, $this->agege, 'Emeka', 'Agege'),
            'mushin' => $this->leader('assembly_coordinator', $this->mushin, $this->mushin, 'Funmi', 'Mushin'),
            'mokola' => $this->leader('assembly_coordinator', $this->mokola, $this->mokola, 'Ade', 'Mokola'),
        ];
    }

    protected function leader(string $roleKey, OrgUnit $scope, OrgUnit $home, string $first, string $last): User
    {
        $member = $this->person($home, $first, $last, password: 'secret-pass-1');
        RoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => Role::where('key', $roleKey)->value('id'),
            'org_unit_id' => $scope->id,
            'starts_on' => now()->subYear()->toDateString(),
        ]);

        return $member->user;
    }

    protected function person(OrgUnit $assembly, string $first, string $last, ?string $password = null, bool $approved = true): Member
    {
        return app(MembershipService::class)->register([
            'first_name' => $first,
            'last_name' => $last,
            'phone' => '+234'.($this->phoneSeq++),
            'email' => strtolower($first.'.'.$last).'@example.test',
        ], $assembly, null, $approved, $password);
    }
}

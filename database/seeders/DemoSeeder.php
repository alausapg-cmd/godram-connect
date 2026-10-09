<?php

namespace Database\Seeders;

use App\Models\ActivityReport;
use App\Models\Announcement;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\ReportEvent;
use App\Models\ReportMedia;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Skill;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Sample data for previews. Every member, report and announcement it makes
 * is flagged is_demo so it can be removed with `php artisan godram:clear-demo`.
 * The organisation names echo GODRAM's history but the structure is illustrative.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'GodramDemo2026';

    protected array $firstNames = [
        'Adebayo', 'Funmilayo', 'Oluwaseun', 'Temitope', 'Babatunde', 'Folake', 'Olumide', 'Bukola', 'Kehinde', 'Taiwo',
        'Ayodeji', 'Omolara', 'Gbenga', 'Yetunde', 'Segun', 'Titilayo', 'Femi', 'Abimbola', 'Tunde', 'Ronke',
        'Chinedu', 'Ngozi', 'Emeka', 'Ifeoma', 'Samuel', 'Grace', 'Daniel', 'Esther', 'Joshua', 'Deborah',
        'Isaac', 'Ruth', 'Ebenezer', 'Mercy', 'Victor', 'Comfort', 'Tolulope', 'Damilola', 'Opeyemi', 'Ifeoluwa',
        'Mayowa', 'Morenike', 'Kunle', 'Bisola', 'Dayo', 'Toyin', 'Wale', 'Jumoke', 'Peter', 'Blessing',
    ];

    protected array $lastNames = [
        'Adeyemi', 'Ogunleye', 'Afolabi', 'Oladipo', 'Akinwale', 'Bamidele', 'Olaniyan', 'Adesanya', 'Ojo', 'Ayodele',
        'Ogundipe', 'Fashola', 'Adebanjo', 'Okafor', 'Eze', 'Balogun', 'Oyeleke', 'Adekunle', 'Ilesanmi', 'Oduya',
        'Akintola', 'Oyewole', 'Fadeyi', 'Ajayi', 'Olatunji', 'Oyelaran', 'Babalola', 'Ogunbiyi', 'Alabi', 'Omotoso',
    ];

    protected array $structure = [
        'Region 1' => [
            'Lagos District' => ['Ayantuga Assembly, Mushin', 'Iwaya Assembly', 'Odo Eran Assembly, Matori'],
            'Agege District' => ['Agege Central Assembly', 'Orile Agege Assembly'],
            'Egbe District' => ['Bolorunpelu Assembly', 'Idimu Assembly'],
        ],
        'Region 2' => [
            'Ojoo District' => ['Headquarters Church, Ojoo', 'Akobo Assembly', 'Moniya Assembly'],
            'Mokola District' => ['Mokola Assembly', 'Sango Assembly'],
        ],
        'Region 3' => [
            'Ife District' => ['Moremi Assembly', 'Campus Assembly, OAU'],
            'Ode Aje District' => ['Ode Aje Central Assembly', 'Iseyin Road Assembly'],
        ],
    ];

    protected MembershipService $membership;

    protected int $phoneSeq = 8030000000;

    public function run(): void
    {
        mt_srand(2026);
        $this->call([AccessSeeder::class, SkillSeeder::class, AchievementRuleSeeder::class]);
        $this->membership = app(MembershipService::class);

        $national = OrgUnit::root() ?? OrgUnit::create(['type' => 'national', 'name' => 'GODRAM National', 'code' => 'NAT', 'city' => 'Ibadan', 'state' => 'Oyo']);

        $assemblies = collect();
        $districts = collect();
        $regions = collect();
        foreach ($this->structure as $regionName => $districtList) {
            $region = OrgUnit::create(['type' => 'region', 'name' => $regionName, 'parent_id' => $national->id]);
            $regions->push($region);
            foreach ($districtList as $districtName => $assemblyNames) {
                $district = OrgUnit::create(['type' => 'district', 'name' => str_replace(' District', '', $districtName), 'parent_id' => $region->id]);
                $districts->push($district);
                foreach ($assemblyNames as $assemblyName) {
                    $assemblies->push(OrgUnit::create(['type' => 'assembly', 'name' => str_replace(' Assembly', '', $assemblyName), 'parent_id' => $district->id]));
                }
            }
        }

        $skillIds = Skill::pluck('id')->all();

        // Coordinators with sign-in accounts.
        $this->coordinator('national_coordinator', $national, $assemblies->first(), 'national@demo.godram.test', 'Oluwafemi', 'Adewale');
        $this->coordinator('system_administrator', $national, $assemblies->first(), 'admin@demo.godram.test', 'Tobi', 'Fakunle');
        $this->coordinator('content_administrator', $national, $assemblies->get(1), 'content@demo.godram.test', 'Kemi', 'Oyebode');
        foreach ($regions as $i => $region) {
            $firstAssembly = OrgUnit::within($region)->ofType('assembly')->first();
            $this->coordinator('regional_coordinator', $region, $firstAssembly, 'region'.($i + 1).'@demo.godram.test');
        }
        foreach ($districts as $district) {
            $firstAssembly = OrgUnit::within($district)->ofType('assembly')->first();
            $this->coordinator('district_coordinator', $district, $firstAssembly, str($district->name)->slug().'.district@demo.godram.test');
        }
        foreach ($assemblies as $assembly) {
            $this->coordinator('assembly_coordinator', $assembly, $assembly, str($assembly->name)->before(',')->slug().'.assembly@demo.godram.test');
        }

        // Ordinary members: 6 to 12 per Assembly.
        foreach ($assemblies as $assembly) {
            $count = mt_rand(6, 12);
            for ($i = 0; $i < $count; $i++) {
                $member = $this->membership->register($this->personData(), $assembly, null, true, $i === 0 ? self::PASSWORD : null);
                $member->skills()->sync(Arr::random($skillIds, mt_rand(1, 3)));
                if ($i === 0) {
                    $member->user->forceFill(['email' => str($assembly->name)->before(',')->slug().'.member@demo.godram.test'])->save();
                    $member->forceFill(['email' => $member->user->email])->save();
                }
                if (mt_rand(1, 12) === 1) {
                    $member->forceFill(['status' => Arr::random(['inactive', 'temporarily_unavailable'])])->save();
                }
            }
            // One self sign-up waiting for confirmation in some Assemblies.
            if (mt_rand(0, 2) === 0) {
                $this->membership->register($this->personData(), $assembly, null, false, self::PASSWORD);
            }
        }

        $this->reports($assemblies, $districts, $regions);
        $this->announcements($national, $districts);
        $this->call(MediaDemoSeeder::class);
        $this->call(AcademyDemoSeeder::class);
        $this->call(ExamDemoSeeder::class);
    }

    protected function coordinator(string $roleKey, OrgUnit $scope, OrgUnit $assembly, string $email, ?string $first = null, ?string $last = null): User
    {
        $data = $this->personData();
        if ($first) {
            $data['first_name'] = $first;
            $data['last_name'] = $last;
        }
        $data['email'] = $email;
        $member = $this->membership->register($data, $assembly, null, true, self::PASSWORD);
        RoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => Role::where('key', $roleKey)->value('id'),
            'org_unit_id' => $scope->id,
            'starts_on' => now()->subYears(mt_rand(1, 4))->toDateString(),
        ]);
        $member->skills()->sync(Arr::random(Skill::pluck('id')->all(), 2));

        return $member->user;
    }

    protected function personData(): array
    {
        $this->phoneSeq += mt_rand(1, 999);

        return [
            'first_name' => Arr::random($this->firstNames),
            'last_name' => Arr::random($this->lastNames),
            'gender' => Arr::random(['male', 'female']),
            'phone' => '+234'.$this->phoneSeq,
            'joined_on' => now()->subDays(mt_rand(30, 3650))->toDateString(),
            'is_demo' => true,
        ];
    }

    protected function reports($assemblies, $districts, $regions): void
    {
        $titles = [
            'drama_presentation' => ['Sunday service drama: The Prodigal Returns', 'Harvest thanksgiving drama', 'Easter drama: Behold the Lamb'],
            'evangelistic_performance' => ['Market square outreach drama', 'Open-air crusade ministration', 'Motor park evangelism drama'],
            'community_outreach' => ['Community outreach at the town hall', 'Hospital visitation drama', 'Prison ministry outreach'],
            'film_screening' => ['Film night: Majemu (Covenant)', 'Campus film screening', 'Village film outreach'],
            'theatre_performance' => ['Stage play: No Second Chance', 'Christmas stage play'],
            'workshop' => ['Acting fundamentals workshop', 'Scriptwriting clinic', 'Stage management workshop'],
            'youth_programme' => ['Youth creative night', 'Teens drama camp'],
            'childrens_programme' => ["Children's Bible drama", "Children's Christmas pageant"],
        ];
        $locations = ['Church auditorium', 'Town hall', 'Market square', 'Community primary school', 'Motor park', 'Open field near the church'];
        $images = collect(config('archive'))->where('kind', 'photo')->pluck('file')->all();

        $statusPool = ['approved', 'approved', 'approved', 'published', 'submitted', 'submitted', 'under_review', 'draft', 'rejected'];

        foreach ($assemblies as $assembly) {
            $creator = $this->coordinatorUser('assembly_coordinator', $assembly);
            $reviewer = $this->coordinatorUser('district_coordinator', $assembly->parent);
            $members = Member::placedWithin($assembly)->approved()->pluck('id')->all();

            for ($i = 0, $n = mt_rand(3, 6); $i < $n; $i++) {
                $type = array_rand($titles);
                $status = Arr::random($statusPool);
                $date = Carbon::now()->subDays(mt_rand(8, 200));
                $report = ActivityReport::create([
                    'org_unit_id' => $assembly->id,
                    'activity_type' => $type,
                    'title' => Arr::random($titles[$type]),
                    'activity_date' => $date->toDateString(),
                    'location' => Arr::random($locations),
                    'description' => 'The team ministered through drama to the congregation and visitors. The message centred on repentance and the love of Christ, followed by prayer and counselling.',
                    'performers_count' => in_array($type, ActivityReport::PERFORMANCE_TYPES) ? mt_rand(6, 25) : null,
                    'attendance_count' => mt_rand(40, 600),
                    'souls_won' => in_array($type, ActivityReport::OUTREACH_TYPES) ? mt_rand(2, 40) : null,
                    'outcome' => 'Well received. Several people stayed back for prayer.',
                    'impact' => 'New contacts were followed up by the evangelism unit.',
                    'status' => $status,
                    'created_by' => $creator?->id,
                    'submitted_at' => $status === 'draft' ? null : $date->copy()->addDays(2),
                    'reviewer_id' => in_array($status, ['approved', 'published', 'rejected']) ? $reviewer?->id : null,
                    'reviewed_at' => in_array($status, ['approved', 'published', 'rejected']) ? $date->copy()->addDays(4) : null,
                    'reject_reason' => $status === 'rejected' ? 'Please add the attendance figure and a photo from the event.' : null,
                    'is_public_highlight' => $status === 'published',
                    'published_at' => $status === 'published' ? $date->copy()->addDays(5) : null,
                    'is_demo' => true,
                ]);
                // Back-date the record so its history reads in order.
                ActivityReport::whereKey($report->id)->update(['created_at' => $date->copy()->addDay(), 'updated_at' => $date->copy()->addDays(2)]);
                $report->participants()->sync(collect(Arr::random($members, min(count($members), mt_rand(3, 6))))->mapWithKeys(fn ($id) => [$id => ['role' => 'performer']])->all());

                $this->history($report, $creator, $reviewer, $status, $date);

                if (in_array($status, ['published', 'approved']) && $images) {
                    $file = Arr::random($images);
                    $path = 'reports/'.$report->id.'/'.$file;
                    Storage::disk('local')->put($path, file_get_contents(public_path('images/archive/'.$file)));
                    ReportMedia::create(['activity_report_id' => $report->id, 'kind' => 'image', 'path' => $path, 'original_name' => $file, 'mime' => 'image/webp']);
                }
            }
        }

        // A District and a Regional report, reviewed from above.
        $district = $districts->first();
        $districtReport = ActivityReport::create([
            'org_unit_id' => $district->id, 'activity_type' => 'workshop', 'title' => 'District acting workshop',
            'activity_date' => now()->subDays(20)->toDateString(), 'location' => 'District headquarters',
            'description' => 'A one-day workshop on voice, movement and character for all Assemblies in the District.',
            'attendance_count' => 64, 'status' => 'submitted', 'submitted_at' => now()->subDays(18),
            'created_by' => $this->coordinatorUser('district_coordinator', $district)?->id, 'is_demo' => true,
        ]);
        $regionReport = ActivityReport::create([
            'org_unit_id' => $regions->first()->id, 'activity_type' => 'regional_performance', 'title' => 'Regional Easter production',
            'activity_date' => now()->subDays(60)->toDateString(), 'location' => 'Regional convention ground',
            'description' => 'Districts in the Region came together for a combined Easter stage production.',
            'performers_count' => 48, 'attendance_count' => 2300, 'souls_won' => 85, 'status' => 'approved',
            'submitted_at' => now()->subDays(55), 'reviewed_at' => now()->subDays(50),
            'created_by' => $this->coordinatorUser('regional_coordinator', $regions->first())?->id,
            'reviewer_id' => $this->coordinatorUser('national_coordinator', OrgUnit::root())?->id, 'is_demo' => true,
        ]);
        ActivityReport::whereKey($districtReport->id)->update(['created_at' => now()->subDays(19)]);
        ActivityReport::whereKey($regionReport->id)->update(['created_at' => now()->subDays(57)]);
    }

    protected function history(ActivityReport $report, ?User $creator, ?User $reviewer, string $status, Carbon $date): void
    {
        $steps = match ($status) {
            'draft' => [],
            'submitted' => [['submitted', 'draft', 'submitted', $creator]],
            'under_review' => [['submitted', 'draft', 'submitted', $creator], ['review_started', 'submitted', 'under_review', $reviewer]],
            'approved' => [['submitted', 'draft', 'submitted', $creator], ['approved', 'submitted', 'approved', $reviewer]],
            'published' => [['submitted', 'draft', 'submitted', $creator], ['approved', 'submitted', 'approved', $reviewer], ['published', 'approved', 'published', $reviewer]],
            'rejected' => [['submitted', 'draft', 'submitted', $creator], ['returned', 'submitted', 'rejected', $reviewer]],
            default => [],
        };
        foreach ($steps as $i => [$action, $from, $to, $user]) {
            ReportEvent::create([
                'activity_report_id' => $report->id, 'user_id' => $user?->id, 'action' => $action,
                'from_status' => $from, 'to_status' => $to,
                'note' => $action === 'returned' ? $report->reject_reason : null,
                'created_at' => $date->copy()->addDays(2 + $i * 2),
            ]);
        }
    }

    protected function coordinatorUser(string $roleKey, OrgUnit $unit): ?User
    {
        $assignment = RoleAssignment::whereHas('role', fn ($q) => $q->where('key', $roleKey))->where('org_unit_id', $unit->id)->first();

        return $assignment?->member?->user;
    }

    protected function announcements(OrgUnit $national, $districts): void
    {
        $author = $this->coordinatorUser('national_coordinator', $national);
        $items = [
            ['GACASA 2026: registration is open', 'The GODRAM Annual Conference of All Saint Artistes holds this December. Every Assembly should send at least two delegates. Registration closes on 30 November.', [['audience' => 'public']], true, 'Register your delegates'],
            ['National Prayer Retreat (GONAPRET)', 'All coordinators and members are invited to the national prayer retreat. Come expecting a fresh fire for the work of the ministry.', [['audience' => 'org_unit', 'org_unit_id' => $national->id]], false, null],
            ['Monthly activity reports are due by the 5th', 'Assembly Coordinators, please submit last month\'s activity reports by the 5th so District Coordinators can review them in good time.', [['audience' => 'role', 'role_key' => 'assembly_coordinator']], false, null],
            ['New on GODRAM TV: Behind the scenes of Valley of Baca', 'Watch the cast share how the film was made and the lives it has touched since its release.', [['audience' => 'public']], false, 'Watch on GODRAM TV'],
        ];
        foreach ($items as $i => [$title, $body, $targets, $pinned, $cta]) {
            $a = Announcement::create([
                'title' => $title, 'body' => $body, 'status' => 'published', 'is_pinned' => $pinned,
                'is_mandatory' => $i === 2, 'org_unit_id' => $national->id, 'author_id' => $author?->id,
                'published_at' => now()->subDays($i * 3 + 1), 'cta_label' => $cta,
                'link_url' => $cta ? ($i === 3 ? config('godram.links.youtube') : null) : null, 'is_demo' => true,
            ]);
            $a->targets()->createMany($targets);
        }

        $district = $districts->first();
        $districtAuthor = $this->coordinatorUser('district_coordinator', $district);
        $a = Announcement::create([
            'title' => $district->name.' District rehearsal this Saturday',
            'body' => 'All Assemblies in the District: combined rehearsal for the Christmas production, 10am at the District headquarters.',
            'status' => 'published', 'org_unit_id' => $district->id, 'author_id' => $districtAuthor?->id,
            'published_at' => now()->subDay(), 'is_demo' => true,
        ]);
        $a->targets()->create(['audience' => 'org_unit', 'org_unit_id' => $district->id]);
    }
}

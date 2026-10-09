<?php

use App\Models\ActivityReport;
use App\Models\Announcement;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Event;
use App\Models\Exam;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Production;
use App\Models\Question;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Spotlight;
use App\Models\Story;
use App\Models\TransferRequest;
use App\Models\User;
use App\Models\Video;
use App\Services\Achievements;
use App\Services\AuditLogger;
use App\Services\Cbt;
use App\Services\MembershipService;
use App\Services\YouTubeChannel;
use App\Support\Phone;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('audit:verify', function (AuditLogger $audit) {
    $broken = $audit->firstBrokenEntry();
    if ($broken === null) {
        $this->info('Audit log intact.');

        return 0;
    }
    $this->error("Audit log chain breaks at entry #{$broken}.");

    return 1;
})->purpose('Check the audit log has not been altered');

Artisan::command('godram:install {--national=GODRAM National}', function (MembershipService $membership) {
    $this->call('db:seed', ['--force' => true]);

    $root = OrgUnit::root() ?? OrgUnit::create(['type' => 'national', 'name' => $this->option('national'), 'code' => 'NAT']);
    $this->info('National unit: '.$root->name);

    if (RoleAssignment::whereHas('role', fn ($q) => $q->where('key', 'system_administrator'))->exists()) {
        $this->info('An administrator already exists.');

        return 0;
    }

    $this->line('Create the first administrator. They also need a home Assembly, so add one now.');
    $district = OrgUnit::create(['type' => 'region', 'name' => $this->ask('First Region name', 'Region 1'), 'parent_id' => $root->id]);
    $district = OrgUnit::create(['type' => 'district', 'name' => $this->ask('First District name'), 'parent_id' => $district->id]);
    $assembly = OrgUnit::create(['type' => 'assembly', 'name' => $this->ask('Their Assembly name'), 'parent_id' => $district->id]);

    $member = $membership->register([
        'first_name' => $this->ask('First name'),
        'last_name' => $this->ask('Last name'),
        'email' => mb_strtolower($this->ask('Email')),
        'phone' => Phone::normalize($this->ask('Phone (optional)')),
    ], $assembly, null, true, $this->secret('Password (at least 8 characters)'));

    foreach (['system_administrator', 'national_coordinator'] as $key) {
        if ($key === 'national_coordinator' && ! $this->confirm('Is this person also the National Coordinator?', true)) {
            continue;
        }
        RoleAssignment::create(['member_id' => $member->id, 'role_id' => Role::where('key', $key)->value('id'), 'org_unit_id' => $root->id, 'starts_on' => now()->toDateString()]);
    }
    $this->info("Done. {$member->full_name} can now sign in as {$member->email}.");
})->purpose('Set up GODRAM CONNECT on a fresh database');

Artisan::command('godram:clear-demo', function () {
    if (! $this->confirm('Remove all demo members, reports, announcements, events, videos, stories, showcase items, Academy training, examinations and certificates?')) {
        return 1;
    }
    DB::transaction(function () {
        foreach (ActivityReport::where('is_demo', true)->get() as $report) {
            Storage::disk('local')->deleteDirectory('reports/'.$report->id);
            $report->delete();
        }
        Announcement::where('is_demo', true)->delete();
        Spotlight::with('subject')->get()->filter(fn ($s) => ! $s->subject || $s->subject->is_demo)->each->delete();
        Story::where('is_demo', true)->delete();
        Video::where('is_demo', true)->delete();
        Event::where('is_demo', true)->delete();
        Production::where('is_demo', true)->delete();
        Exam::where('is_demo', true)->delete();
        Certificate::where('is_demo', true)->delete();
        Question::where('is_demo', true)->whereDoesntHave('uses')->delete();
        Course::where('is_demo', true)->delete();
        $ids = Member::where('is_demo', true)->pluck('id');
        User::whereIn('member_id', $ids)->update(['is_active' => false, 'member_id' => null]);
        RoleAssignment::whereIn('member_id', $ids)->delete();
        TransferRequest::whereIn('member_id', $ids)->delete();
        Member::whereIn('id', $ids)->delete();
    });
    $this->info('Demo data removed. The organisation structure was kept; edit or replace it under Administration.');
})->purpose('Remove sample data before real use');

Artisan::command('godram:sync-youtube', function (YouTubeChannel $channel) {
    $added = $channel->import();
    $this->info($added ? "Added {$added} new GODRAM TV ".str('video')->plural($added).' to the Watch centre.' : 'No new GODRAM TV videos.');
})->purpose('Add new GODRAM TV uploads to the Watch centre');

Schedule::command('godram:sync-youtube')->hourly()->withoutOverlapping();

Artisan::command('godram:finalise-exams', function (Cbt $cbt) {
    $count = $cbt->finaliseExpired();
    if ($count) {
        $this->info("Submitted {$count} ".str('attempt')->plural($count).' whose time had run out.');
    }
})->purpose('Submit examination attempts whose time ran out while the candidate was offline');

Artisan::command('godram:achievements', function (Achievements $achievements) {
    $this->info($achievements->evaluateAll().' new achievements awarded.');
})->purpose('Check every member against the achievement rules');

Schedule::command('godram:finalise-exams')->everyMinute()->withoutOverlapping();
Schedule::command('godram:achievements')->dailyAt('02:30')->withoutOverlapping();

<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/** Roles and permissions from the architecture document. Safe to re-run. */
class AccessSeeder extends Seeder
{
    public const PERMISSIONS = [
        'members.view' => 'View members in scope',
        'members.create' => 'Register members',
        'members.update' => 'Update member records',
        'members.approve_signup' => 'Confirm self sign-ups',
        'members.transfer.request' => 'Request member transfers',
        'members.transfer.approve' => 'Approve incoming transfers',
        'members.export' => 'Export member lists',
        'reports.view' => 'View activity reports in scope',
        'reports.create' => 'Create and submit activity reports',
        'reports.approve' => 'Review and approve reports from the level below',
        'reports.publish' => 'Publish approved reports as public highlights',
        'reports.export' => 'Export reports',
        'announcements.create' => 'Write announcements for people in scope',
        'announcements.publish' => 'Approve public and national announcements',
        'production_teams.manage' => 'Form regional production teams',
        'events.create' => 'Create and manage events for your area',
        'media.submit' => 'Suggest GODRAM TV videos for the Watch centre',
        'media.manage' => 'Publish videos, showcase productions and spotlights',
        'stories.review' => 'Review and publish GODRAM stories',
        'analytics.view' => 'See analytics and trends',
        'org.manage' => 'Manage Regions, Districts and Assemblies',
        'roles.manage' => 'Assign and end roles',
        'audit.view' => 'Read the audit log',
        'settings.manage' => 'Change platform settings',
    ];

    public const ROLES = [
        'assembly_coordinator' => ['Assembly Coordinator', 'assembly', [
            'members.view', 'members.create', 'members.update', 'members.approve_signup',
            'members.transfer.request', 'members.transfer.approve',
            'reports.view', 'reports.create', 'announcements.create',
            'events.create', 'media.submit',
        ]],
        'district_coordinator' => ['District Coordinator', 'district', [
            'members.view', 'members.approve_signup', 'members.transfer.request', 'members.transfer.approve', 'members.export',
            'reports.view', 'reports.create', 'reports.approve', 'reports.publish', 'reports.export',
            'announcements.create', 'analytics.view', 'events.create', 'media.submit',
        ]],
        'regional_coordinator' => ['Regional Coordinator', 'region', [
            'members.view', 'members.transfer.approve', 'members.export',
            'reports.view', 'reports.create', 'reports.approve', 'reports.export',
            'announcements.create', 'production_teams.manage', 'analytics.view', 'events.create', 'media.submit',
        ]],
        'national_coordinator' => ['National Coordinator', 'national', [
            'members.view', 'members.transfer.approve', 'members.export',
            'reports.view', 'reports.create', 'reports.approve', 'reports.publish', 'reports.export',
            'announcements.create', 'announcements.publish', 'production_teams.manage',
            'analytics.view', 'audit.view', 'roles.manage',
            'events.create', 'media.submit', 'media.manage', 'stories.review',
        ]],
        'facilitator' => ['Facilitator', null, []],
        'examiner' => ['Examiner', null, []],
        'training_administrator' => ['Training Administrator', null, ['announcements.create']],
        'content_administrator' => ['Content Administrator', 'national', ['announcements.create', 'announcements.publish', 'reports.view', 'reports.publish', 'events.create', 'media.submit', 'media.manage', 'stories.review']],
        'system_administrator' => ['System Administrator', 'national', ['org.manage', 'roles.manage', 'audit.view', 'settings.manage']],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $key => $description) {
            Permission::updateOrCreate(['key' => $key], ['description' => $description]);
        }

        $sort = 0;
        foreach (self::ROLES as $key => [$name, $scope, $permissions]) {
            $role = Role::updateOrCreate(['key' => $key], ['name' => $name, 'scope_type' => $scope, 'sort' => $sort++]);
            $role->permissions()->sync(Permission::whereIn('key', $permissions)->pluck('id'));
        }
    }
}

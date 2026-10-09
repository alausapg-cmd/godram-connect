<?php

/*
| What people can be told about, and how. In-app notices always arrive; each person
| chooses email and push per category on /notifications/settings. Categories marked
| "locked" cannot be turned off, because the spec makes mandatory leadership
| communication non-optional.
*/
return [
    'categories' => [
        'leadership' => [
            'icon' => 'shield',
            'label' => 'Leadership messages',
            'description' => 'Mandatory announcements from your Coordinators and news of your own appointments.',
            'email' => true, 'push' => true, 'locked' => true,
        ],
        'announcements' => [
            'icon' => 'megaphone',
            'label' => 'Announcements',
            'description' => 'New announcements for your Assembly, District, Region or role.',
            'email' => false, 'push' => true,
        ],
        'events' => [
            'icon' => 'calendar',
            'label' => 'Events',
            'description' => 'New events near you, reminders for events you registered for, and changes to them.',
            'email' => false, 'push' => true,
        ],
        'live' => [
            'icon' => 'live',
            'label' => 'Live now',
            'description' => 'When a livestream or live training you can join is about to start.',
            'email' => false, 'push' => true,
        ],
        'videos' => [
            'icon' => 'play',
            'label' => 'New videos',
            'description' => 'New GODRAM TV videos in the Watch centre.',
            'email' => false, 'push' => false,
        ],
        'training' => [
            'icon' => 'academy',
            'label' => 'Training',
            'description' => 'New Academy training for you, live session reminders and assignment due dates.',
            'email' => true, 'push' => true,
        ],
        'reports' => [
            'icon' => 'report',
            'label' => 'Reports',
            'description' => 'Reports waiting for your review, decisions on your reports and monthly reminders.',
            'email' => true, 'push' => true,
        ],
        'exams' => [
            'icon' => 'pen',
            'label' => 'Examinations',
            'description' => 'Examinations opening for you and your results.',
            'email' => true, 'push' => true,
        ],
        'certificates' => [
            'icon' => 'award',
            'label' => 'Certificates',
            'description' => 'When a certificate is issued to you.',
            'email' => true, 'push' => true,
        ],
        'achievements' => [
            'icon' => 'star',
            'label' => 'Achievements',
            'description' => 'Milestones and awards you reach.',
            'email' => false, 'push' => true,
        ],
        'account' => [
            'icon' => 'user',
            'label' => 'My account',
            'description' => 'Your membership being confirmed and other changes to your account.',
            'email' => true, 'push' => false,
        ],
    ],
];

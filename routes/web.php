<?php

use App\Http\Controllers\Academy\LearnController;
use App\Http\Controllers\Academy\ManageController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\OrgUnitController;
use App\Http\Controllers\Admin\RoleAssignmentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CoverController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\Exams\CertificateController;
use App\Http\Controllers\Exams\ExamAdminController;
use App\Http\Controllers\Exams\ExamController;
use App\Http\Controllers\Exams\QuestionBankController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportMediaController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShowcaseController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

// Public experience
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/archive', [PublicController::class, 'archive'])->name('archive');
Route::get('/highlights', [PublicController::class, 'highlights'])->name('highlights');
Route::get('/highlights/{report}', [PublicController::class, 'highlight'])->name('highlights.show');
Route::get('/network/{unit?}', [NetworkController::class, 'show'])->name('network');
Route::get('/news', [AnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/news/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
Route::get('/news/{announcement}/image', [AnnouncementController::class, 'image'])->name('announcements.image');
Route::get('/media/reports/{media}', [ReportMediaController::class, 'show'])->name('report-media.show');
Route::get('/offline', fn () => view('offline'))->name('offline');
Route::get('/search', SearchController::class)->name('search');
Route::get('/covers/{type}/{id}', [CoverController::class, 'show'])->whereNumber('id')->name('covers.show');
Route::get('/share/{type}/{slug}.png', [CoverController::class, 'share'])->name('share.card');
Route::get('/verify', [CertificateController::class, 'lookup'])->name('certificates.lookup');
Route::get('/verify/{number}', [CertificateController::class, 'verify'])->middleware('throttle:30,1')->name('certificates.verify');

// Sign-in
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/join', [RegisterController::class, 'show'])->name('register');
    Route::post('/join', [RegisterController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/change-password', [PasswordController::class, 'showChange'])->name('password.change');
    Route::put('/change-password', [PasswordController::class, 'change'])->name('password.change.update');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::get('/me', [ProfileController::class, 'show'])->name('profile');
    Route::get('/me/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/me', [ProfileController::class, 'update'])->name('profile.update');

    // Membership
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/members/export', [MemberController::class, 'export'])->name('members.export');
    Route::get('/members/create', [MemberController::class, 'create'])->name('members.create');
    Route::post('/members', [MemberController::class, 'store'])->name('members.store');
    Route::get('/members/{member}', [MemberController::class, 'show'])->name('members.show');
    Route::get('/members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
    Route::put('/members/{member}', [MemberController::class, 'update'])->name('members.update');
    Route::post('/members/{member}/status', [MemberController::class, 'status'])->name('members.status');
    Route::post('/members/{member}/reset-password', [MemberController::class, 'resetPassword'])->name('members.reset-password');
    Route::post('/members/{member}/account', [MemberController::class, 'createAccount'])->name('members.account');

    Route::get('/signups', [SignupController::class, 'index'])->name('signups.index');
    Route::post('/signups/{member}/approve', [SignupController::class, 'approve'])->name('signups.approve');
    Route::post('/signups/{member}/reject', [SignupController::class, 'reject'])->name('signups.reject');

    Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::get('/members/{member}/transfer', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('/members/{member}/transfer', [TransferController::class, 'store'])->name('transfers.store');
    Route::post('/transfers/{transfer}/decide', [TransferController::class, 'decide'])->name('transfers.decide');

    // Reporting
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports', [ReportController::class, 'start'])->name('reports.start');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/edit', [ReportController::class, 'edit'])->name('reports.edit');
    Route::put('/reports/{report}', [ReportController::class, 'update'])->name('reports.update');
    Route::patch('/reports/{report}/autosave', [ReportController::class, 'autosave'])->name('reports.autosave');
    Route::post('/reports/{report}/submit', [ReportController::class, 'submit'])->name('reports.submit');
    Route::post('/reports/{report}/review', [ReportController::class, 'review'])->name('reports.review');
    Route::post('/reports/{report}/comment', [ReportController::class, 'comment'])->name('reports.comment');
    Route::post('/reports/{report}/publish', [ReportController::class, 'publish'])->name('reports.publish');
    Route::post('/reports/{report}/archive', [ReportController::class, 'archive'])->name('reports.archive');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');
    Route::post('/reports/{report}/media', [ReportMediaController::class, 'store'])->name('report-media.store');
    Route::delete('/report-media/{media}', [ReportMediaController::class, 'destroy'])->name('report-media.destroy');

    // Announcements
    Route::get('/announcements/manage', [AnnouncementController::class, 'manage'])->name('announcements.manage');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::post('/announcements/{announcement}/decide', [AnnouncementController::class, 'decide'])->name('announcements.decide');
    Route::post('/announcements/{announcement}/archive', [AnnouncementController::class, 'archive'])->name('announcements.archive');

    // Events
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('/events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    Route::delete('/events/{event}/register', [EventController::class, 'unregister'])->name('events.unregister');
    Route::get('/events/{event}/registrations', [EventController::class, 'registrations'])->name('events.registrations');
    Route::post('/events/{event}/registrations/{registration}/attended', [EventController::class, 'markAttended'])->name('events.attended');

    // GODRAM TV
    Route::get('/videos/manage', [VideoController::class, 'manage'])->name('videos.manage');
    Route::get('/videos/create', [VideoController::class, 'create'])->name('videos.create');
    Route::post('/videos', [VideoController::class, 'store'])->name('videos.store');
    Route::get('/videos/{video}/edit', [VideoController::class, 'edit'])->name('videos.edit');
    Route::put('/videos/{video}', [VideoController::class, 'update'])->name('videos.update');
    Route::post('/videos/{video}/decide', [VideoController::class, 'decide'])->name('videos.decide');

    // Creative Showcase
    Route::get('/showcase/create', [ShowcaseController::class, 'create'])->name('showcase.create');
    Route::post('/showcase', [ShowcaseController::class, 'store'])->name('showcase.store');
    Route::get('/showcase/{production}/edit', [ShowcaseController::class, 'edit'])->name('showcase.edit');
    Route::put('/showcase/{production}', [ShowcaseController::class, 'update'])->name('showcase.update');
    Route::post('/showcase/{production}/spotlight', [ShowcaseController::class, 'spotlight'])->name('showcase.spotlight');

    // Stories
    Route::get('/stories/mine', [StoryController::class, 'mine'])->name('stories.mine');
    Route::get('/stories/manage', [StoryController::class, 'manage'])->name('stories.manage');
    Route::get('/stories/create', [StoryController::class, 'create'])->name('stories.create');
    Route::post('/stories', [StoryController::class, 'store'])->name('stories.store');
    Route::get('/stories/{story}/edit', [StoryController::class, 'edit'])->name('stories.edit');
    Route::put('/stories/{story}', [StoryController::class, 'update'])->name('stories.update');
    Route::post('/stories/{story}/review', [StoryController::class, 'review'])->name('stories.review');

    // GODRAM Virtual Academy: members
    Route::get('/my-learning', [LearnController::class, 'mine'])->name('academy.mine');
    Route::get('/academy/submissions/{submission}/file', [LearnController::class, 'submissionFile'])->name('academy.submission-file');

    // GODRAM Virtual Academy: training administrators and facilitators
    Route::prefix('academy/manage')->name('academy.manage.')->controller(ManageController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{course}', 'build')->name('build');
        Route::get('/{course}/edit', 'edit')->name('edit');
        Route::put('/{course}', 'update')->name('update');
        Route::post('/{course}/status', 'status')->name('status');
        Route::post('/{course}/sessions', 'storeSession')->name('sessions.store');
        Route::put('/{course}/sessions/{session}', 'updateSession')->name('sessions.update');
        Route::delete('/{course}/sessions/{session}', 'destroySession')->name('sessions.destroy');
        Route::post('/{course}/sessions/{session}/move', 'moveSession')->name('sessions.move');
        Route::post('/{course}/sessions/{session}/room', 'roomToggle')->name('sessions.room');
        Route::post('/{course}/sessions/{session}/attendance', 'attendance')->name('sessions.attendance');
        Route::get('/{course}/lessons/create', 'createLesson')->name('lessons.create');
        Route::post('/{course}/lessons', 'storeLesson')->name('lessons.store');
        Route::get('/{course}/lessons/{lesson}/edit', 'editLesson')->name('lessons.edit');
        Route::put('/{course}/lessons/{lesson}', 'updateLesson')->name('lessons.update');
        Route::delete('/{course}/lessons/{lesson}', 'destroyLesson')->name('lessons.destroy');
        Route::post('/{course}/lessons/{lesson}/move', 'moveLesson')->name('lessons.move');
        Route::post('/{course}/resources', 'storeResource')->name('resources.store');
        Route::delete('/{course}/resources/{resource}', 'destroyResource')->name('resources.destroy');
        Route::post('/{course}/prompts', 'storePrompt')->name('prompts.store');
        Route::post('/{course}/prompts/{prompt}', 'updatePrompt')->name('prompts.update');
        Route::post('/{course}/assignments', 'storeAssignment')->name('assignments.store');
        Route::put('/{course}/assignments/{assignment}', 'updateAssignment')->name('assignments.update');
        Route::delete('/{course}/assignments/{assignment}', 'destroyAssignment')->name('assignments.destroy');
        Route::get('/{course}/submissions', 'submissions')->name('submissions');
        Route::post('/{course}/submissions/{submission}', 'review')->name('submissions.review');
        Route::get('/{course}/questions', 'questions')->name('questions');
        Route::post('/{course}/questions/{question}', 'moderate')->name('questions.moderate');
        Route::get('/{course}/people', 'people')->name('people');
    });
    Route::get('/academy/overview', [ManageController::class, 'overview'])->name('academy.overview');

    Route::controller(LearnController::class)->prefix('academy/{course}')->name('academy.')->scopeBindings()->group(function () {
        Route::post('/enrol', 'enrol')->name('enrol');
        Route::delete('/enrol', 'withdraw')->name('withdraw');
        Route::post('/lessons/{lesson}/complete', 'complete')->name('complete');
        Route::get('/lessons/{lesson}/audio', 'audio')->name('audio');
        Route::get('/resources/{resource}', 'resource')->name('resource');
        Route::post('/prompts/{prompt}', 'respond')->name('respond');
        Route::get('/live/{session}', 'room')->name('room');
        Route::get('/live/{session}/feed', 'feed')->name('feed');
        Route::post('/questions', 'ask')->middleware('throttle:20,1')->name('ask');
        Route::get('/assignments/{assignment}', 'assignment')->name('assignment');
        Route::post('/assignments/{assignment}', 'submit')->name('submit');
    });

    // CBT examinations: question bank and exam administration
    Route::prefix('question-bank')->name('questions.')->controller(QuestionBankController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/categories', 'storeCategory')->name('categories.store');
        Route::get('/{question}/edit', 'edit')->name('edit');
        Route::put('/{question}', 'update')->name('update');
        Route::post('/{question}/review', 'review')->name('review');
        Route::get('/{question}/media', 'media')->name('media');
    });
    Route::prefix('exams/manage')->name('exams.manage.')->controller(ExamAdminController::class)->scopeBindings()->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{exam}', 'show')->name('show');
        Route::get('/{exam}/edit', 'edit')->name('edit');
        Route::put('/{exam}', 'update')->name('update');
        Route::post('/{exam}/status', 'status')->name('status');
        Route::post('/{exam}/release', 'release')->name('release');
        Route::get('/{exam}/attempts/{attempt}', 'attempt')->name('attempt');
        Route::post('/{exam}/attempts/{attempt}/mark', 'mark')->name('mark');
        Route::post('/{exam}/attempts/{attempt}/extend', 'extend')->name('extend');
    });

    // CBT examinations: candidates
    Route::controller(ExamController::class)->name('exams.')->group(function () {
        Route::get('/exams', 'index')->name('index');
        Route::get('/exams/attempts/{attempt}', 'sit')->name('sit');
        Route::get('/exams/attempts/{attempt}/state', 'state')->middleware('throttle:60,1')->name('state');
        Route::post('/exams/attempts/{attempt}/answers', 'save')->middleware('throttle:240,1')->name('save');
        Route::post('/exams/attempts/{attempt}/signal', 'signal')->middleware('throttle:60,1')->name('signal');
        Route::post('/exams/attempts/{attempt}/submit', 'submit')->name('submit');
        Route::get('/exams/attempts/{attempt}/result', 'result')->name('result');
        Route::get('/exams/attempts/{attempt}/media/{position}', 'media')->whereNumber('position')->name('media');
        Route::get('/exams/{exam}', 'show')->name('show');
        Route::post('/exams/{exam}/start', 'start')->middleware('throttle:10,1')->name('start');
    });

    // Certificates and achievements
    Route::get('/my-certificates', [CertificateController::class, 'mine'])->name('certificates.mine');
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::prefix('certificates/manage')->name('certificates.manage.')->controller(CertificateController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/issue', 'create')->name('create');
        Route::get('/designs/{kind}', 'specimen')->name('specimen');
        Route::post('/issue', 'store')->name('store');
        Route::post('/signatures', 'signature')->name('signature');
        Route::post('/rules', 'storeRule')->name('rules.store');
        Route::put('/rules/{rule}', 'updateRule')->name('rules.update');
        Route::post('/{certificate}/revoke', 'revoke')->name('revoke');
    });

    // Administration
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/structure', [OrgUnitController::class, 'index'])->name('units.index');
        Route::post('/structure', [OrgUnitController::class, 'store'])->name('units.store');
        Route::put('/structure/{unit}', [OrgUnitController::class, 'update'])->name('units.update');
        Route::get('/roles', [RoleAssignmentController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleAssignmentController::class, 'store'])->name('roles.store');
        Route::post('/roles/{assignment}/end', [RoleAssignmentController::class, 'end'])->name('roles.end');
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

// Public pages with slugs come last, so /events/create and the like are matched first.
Route::get('/events', [EventController::class, 'index'])->name('events');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/events/{event}/calendar.ics', [EventController::class, 'calendar'])->name('events.calendar');
Route::post('/events/{event}/register', [EventController::class, 'register'])->middleware('throttle:10,1')->name('events.register');
Route::get('/watch', [VideoController::class, 'index'])->name('watch');
Route::get('/watch/{video}', [VideoController::class, 'show'])->name('watch.show');
Route::get('/showcase', [ShowcaseController::class, 'index'])->name('showcase');
Route::get('/showcase/{production}', [ShowcaseController::class, 'show'])->name('showcase.show');
Route::get('/stories', [StoryController::class, 'index'])->name('stories');
Route::get('/stories/{story}', [StoryController::class, 'show'])->name('stories.show');
Route::get('/stories/{story}/audio', [StoryController::class, 'audio'])->name('stories.audio');
Route::get('/academy', [LearnController::class, 'index'])->name('academy');
Route::get('/academy/{course}', [LearnController::class, 'show'])->name('academy.show');
Route::get('/academy/{course}/lessons/{lesson}', [LearnController::class, 'lesson'])->scopeBindings()->name('academy.lesson');

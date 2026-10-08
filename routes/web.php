<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\OrgUnitController;
use App\Http\Controllers\Admin\RoleAssignmentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportMediaController;
use App\Http\Controllers\SignupController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

// Public experience
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/archive', [PublicController::class, 'archive'])->name('archive');
Route::get('/highlights', [PublicController::class, 'highlights'])->name('highlights');
Route::get('/highlights/{report}', [PublicController::class, 'highlight'])->name('highlights.show');
Route::get('/watch', [PublicController::class, 'watch'])->name('watch');
Route::get('/events', [PublicController::class, 'events'])->name('events');
Route::get('/academy', [PublicController::class, 'academy'])->name('academy');
Route::get('/network/{unit?}', [NetworkController::class, 'show'])->name('network');
Route::get('/news', [AnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/news/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
Route::get('/news/{announcement}/image', [AnnouncementController::class, 'image'])->name('announcements.image');
Route::get('/media/reports/{media}', [ReportMediaController::class, 'show'])->name('report-media.show');
Route::get('/offline', fn () => view('offline'))->name('offline');

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

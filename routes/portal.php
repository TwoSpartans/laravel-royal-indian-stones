<?php

use App\Http\Controllers\Portal\AccountController;
use App\Http\Controllers\Portal\AttendanceController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DirectoryController;
use App\Http\Controllers\Portal\ExpenseController;
use App\Http\Controllers\Portal\InvitationController;
use App\Http\Controllers\Portal\LeaveController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\MfaController;
use App\Http\Controllers\Portal\NoticeController;
use App\Http\Controllers\Portal\PasswordController;
use App\Http\Controllers\Portal\PayrollController;
use App\Http\Controllers\Portal\ReportController;
use App\Http\Controllers\Portal\TaskController;
use App\Http\Controllers\Portal\WorkplaceController;
use App\Http\Middleware\NoindexAdminResponses;
use Illuminate\Support\Facades\Route;

Route::middleware(NoindexAdminResponses::class)->prefix('portal')->name('portal.')->group(function () {
    Route::view('/privacy', 'portal.notice', ['public' => true])->name('privacy');

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:portal-login')->name('login.store');
        Route::get('forgot-password', [PasswordController::class, 'request'])->name('password.request');
        Route::post('forgot-password', [PasswordController::class, 'email'])->middleware('throttle:portal-login')->name('password.email');
        Route::get('reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
        Route::post('reset-password', [PasswordController::class, 'update'])->middleware('throttle:portal-login')->name('password.update');
        Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
        Route::post('invitation/{token}', [InvitationController::class, 'accept'])->middleware('throttle:portal-login')->name('invitation.accept');
    });

    Route::middleware(['auth', 'portal.account'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('mfa/setup', [MfaController::class, 'setup'])->name('mfa.setup');
        Route::post('mfa/setup', [MfaController::class, 'confirm'])->middleware('throttle:portal-login')->name('mfa.confirm');
        Route::get('mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
        Route::post('mfa/challenge', [MfaController::class, 'verify'])->middleware('throttle:portal-login')->name('mfa.verify');

        Route::middleware('portal.mfa')->group(function () {
            Route::get('notice', [NoticeController::class, 'show'])->name('notice');
            Route::post('notice', [NoticeController::class, 'acknowledge'])->name('notice.acknowledge');

            Route::middleware(['portal.notice', 'portal.section'])->group(function () {
                Route::get('/', DashboardController::class)->name('home');
                Route::get('account', [AccountController::class, 'show'])->name('account');
                Route::post('account/password', [PasswordController::class, 'change'])->name('account.password');
                Route::get('notifications', [AccountController::class, 'inbox'])->name('notifications');
                Route::post('notifications/{notification}/read', [AccountController::class, 'read'])->name('notifications.read');

                Route::get('people', [DirectoryController::class, 'index'])->name('people.index');
                Route::get('people/create', [DirectoryController::class, 'create'])->name('people.create');
                Route::post('people', [DirectoryController::class, 'store'])->name('people.store');
                Route::get('people/{member}', [DirectoryController::class, 'show'])->whereNumber('member')->name('people.show');
                Route::get('people/{member}/edit', [DirectoryController::class, 'edit'])->whereNumber('member')->name('people.edit');
                Route::put('people/{member}', [DirectoryController::class, 'update'])->whereNumber('member')->name('people.update');
                Route::post('people/{member}/deactivate', [DirectoryController::class, 'deactivate'])->whereNumber('member')->name('people.deactivate');
                Route::post('people/{member}/reactivate', [DirectoryController::class, 'reactivate'])->whereNumber('member')->name('people.reactivate');
                Route::post('people/{member}/offboard', [DirectoryController::class, 'offboard'])->whereNumber('member')->name('people.offboard');
                Route::post('people/{member}/contacts', [DirectoryController::class, 'contact'])->whereNumber('member')->name('people.contacts.store');
                Route::post('people/{member}/documents', [DirectoryController::class, 'document'])->whereNumber('member')->name('people.documents.store');
                Route::get('documents/{document}', [DirectoryController::class, 'downloadDocument'])->name('documents.download');
                Route::post('checklist/{item}', [DirectoryController::class, 'checklist'])->name('checklist.update');
                Route::post('people/{member}/balances', [DirectoryController::class, 'balance'])->whereNumber('member')->name('people.balances.update');

                Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
                Route::post('attendance/clock', [AttendanceController::class, 'clock'])->middleware('throttle:portal-clock')->name('attendance.clock');
                Route::post('attendance/corrections', [AttendanceController::class, 'correct'])->name('attendance.corrections.store');
                Route::post('attendance/corrections/{correction}', [AttendanceController::class, 'decideCorrection'])->name('attendance.corrections.decide');
                Route::post('attendance/overtime', [AttendanceController::class, 'overtime'])->name('attendance.overtime.store');
                Route::post('attendance/overtime/{overtime}', [AttendanceController::class, 'decideOvertime'])->name('attendance.overtime.decide');

                Route::get('leave', [LeaveController::class, 'index'])->name('leave.index');
                Route::post('leave', [LeaveController::class, 'store'])->name('leave.store');
                Route::post('leave/{leave}', [LeaveController::class, 'decide'])->name('leave.decide');

                Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
                Route::get('tasks/create', [TaskController::class, 'create'])->name('tasks.create');
                Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
                Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
                Route::get('tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
                Route::put('tasks/{task}', [TaskController::class, 'revise'])->name('tasks.update');
                Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
                Route::post('tasks/{task}/status', [TaskController::class, 'update'])->name('tasks.status');
                Route::post('tasks/{task}/comments', [TaskController::class, 'comment'])->name('tasks.comments.store');
                Route::post('tasks/{task}/attachments', [TaskController::class, 'attachment'])->name('tasks.attachments.store');
                Route::get('attachments/{attachment}', [TaskController::class, 'download'])->name('attachments.download');

                Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
                Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
                Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
                Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
                Route::post('expenses/{expense}/decision', [ExpenseController::class, 'decide'])->name('expenses.decide');
                Route::post('expenses/{expense}/payment', [ExpenseController::class, 'payment'])->name('expenses.payment');
                Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');

                Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
                Route::post('payroll/structures', [PayrollController::class, 'structure'])->name('payroll.structures.store');
                Route::post('payroll/adjustments', [PayrollController::class, 'adjustment'])->name('payroll.adjustments.store');
                Route::post('payroll/prepare', [PayrollController::class, 'prepare'])->name('payroll.prepare');
                Route::post('payroll/{payslip}/publish', [PayrollController::class, 'publish'])->name('payroll.publish');
                Route::get('payroll/{payslip}', [PayrollController::class, 'show'])->name('payroll.show');
                Route::post('payroll/{payslip}/payment', [PayrollController::class, 'payment'])->name('payroll.payment');

                Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
                Route::post('reports', [ReportController::class, 'export'])->name('reports.export');

                Route::get('workplace', [WorkplaceController::class, 'index'])->name('workplace.index');
                Route::post('workplace/networks', [WorkplaceController::class, 'network'])->name('workplace.networks.store');
                Route::delete('workplace/networks/{network}', [WorkplaceController::class, 'destroyNetwork'])->name('workplace.networks.destroy');
                Route::post('workplace/holidays', [WorkplaceController::class, 'holiday'])->name('workplace.holidays.store');
                Route::put('workplace/leave-types/{leaveType}', [WorkplaceController::class, 'leaveType'])->name('workplace.leave-types.update');
                Route::post('workplace/devices/{device}', [WorkplaceController::class, 'device'])->name('workplace.devices.update');
                Route::post('workplace/remote', [WorkplaceController::class, 'remote'])->name('workplace.remote.store');
                Route::post('workplace/events/{event}', [WorkplaceController::class, 'review'])->name('workplace.events.review');
            });
        });
    });
});

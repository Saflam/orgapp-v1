<?php

use Illuminate\Support\Facades\Route;
use Modules\Events\Http\Middleware\EnsureEventListViewPermission;
use Modules\Events\Http\Middleware\ResolveEventAuthorizationContext;
use Modules\Events\Http\Controllers\Api\EventAttendanceController;
use Modules\Events\Http\Controllers\Api\EventAttendanceCorrectionController;
use Modules\Events\Http\Controllers\Api\EventAttendanceAuditController;
use Modules\Events\Http\Controllers\Api\EventCategoryController;
use Modules\Events\Http\Controllers\Api\EventController;
use Modules\Events\Http\Controllers\Api\EventParticipantController;
use Modules\Events\Http\Controllers\Api\EventReportController;
use Modules\Events\Http\Controllers\Api\EventPassController;
use Modules\Events\Http\Controllers\Api\EventPassAttendeeController;
use Modules\Events\Http\Controllers\Api\EventVolunteerController;
use Modules\Events\Http\Controllers\Api\PublicEventRegistrationController;

Route::middleware(['auth', 'organization', 'organization.access', 'module:Events', ResolveEventAuthorizationContext::class])
    ->prefix('v1/events')
    ->name('events.')
    ->group(function (): void {
        Route::middleware(EnsureEventListViewPermission::class)->get('/', [EventController::class, 'index'])->name('index');
        Route::middleware(['permission:events.attendance.scan', 'throttle:events-attendance'])->post('/attendance/scan', [EventAttendanceController::class, 'scan'])->name('attendance.scan');
        Route::middleware('permission:events.attendance.audit.view')->get('/{event}/attendance/corrections', [EventAttendanceCorrectionController::class, 'index'])->whereNumber('event')->name('attendance.corrections.index');
        Route::middleware('permission:events.attendance.correct')->post('/{event}/passes/{pass}/attendance-corrections', [EventAttendanceCorrectionController::class, 'store'])->whereNumber('event')->whereNumber('pass')->name('attendance.corrections.store');
        Route::middleware('permission:events.attendance.audit.view')->get('/{event}/attendance/scans', [EventAttendanceAuditController::class, 'index'])->whereNumber('event')->name('attendance.scans.index');
        Route::middleware('permission:events.volunteers.manage')->get('/{event}/volunteers', [EventVolunteerController::class, 'index'])->whereNumber('event')->name('volunteers.index');
        Route::middleware('permission:events.volunteers.manage')->post('/{event}/volunteers', [EventVolunteerController::class, 'store'])->whereNumber('event')->name('volunteers.store');
        Route::middleware('permission:events.volunteers.manage')->delete('/{event}/volunteers/{user}', [EventVolunteerController::class, 'destroy'])->whereNumber('event')->whereNumber('user')->name('volunteers.destroy');
        Route::middleware('permission:events.manage')->post('/', [EventController::class, 'store'])->name('store');

        Route::middleware('permission:events.view')->get('/categories', [EventCategoryController::class, 'index'])->name('categories.index');
        Route::middleware('permission:events.manage')->post('/categories', [EventCategoryController::class, 'store'])->name('categories.store');
        Route::middleware('permission:events.view')->get('/categories/{category}', [EventCategoryController::class, 'show'])->whereNumber('category')->name('categories.show');
        Route::middleware('permission:events.manage')->put('/categories/{category}', [EventCategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
        Route::middleware('permission:events.manage')->delete('/categories/{category}', [EventCategoryController::class, 'destroy'])->whereNumber('category')->name('categories.destroy');

        Route::get('/reports/summary', [EventReportController::class, 'organizationSummary'])->name('reports.summary');
        Route::get('/reports/categories', [EventReportController::class, 'categories'])->name('reports.categories');

        Route::middleware('permission:events.view')->get('/{event}', [EventController::class, 'show'])->whereNumber('event')->name('show');
        Route::middleware('permission:events.manage')->put('/{event}', [EventController::class, 'update'])->whereNumber('event')->name('update');
        Route::middleware('permission:events.publish')->post('/{event}/publish', [EventController::class, 'publish'])->whereNumber('event')->name('publish');
        Route::middleware('permission:events.publish')->post('/{event}/cancel', [EventController::class, 'cancel'])->whereNumber('event')->name('cancel');
        Route::middleware('permission:events.view')->get('/{event}/participants', [EventParticipantController::class, 'index'])->whereNumber('event')->name('participants.index');
        Route::get('/{event}/report', [EventReportController::class, 'summary'])->whereNumber('event')->name('report.summary');
        Route::get('/{event}/reports/registrations', [EventReportController::class, 'registrations'])->whereNumber('event')->name('reports.registrations');
        Route::get('/{event}/reports/attendance', [EventReportController::class, 'attendance'])->whereNumber('event')->name('reports.attendance');
        Route::get('/{event}/reports/passes', [EventReportController::class, 'passes'])->whereNumber('event')->name('reports.passes');
        Route::middleware('permission:events.invitations.send')->post('/{event}/participants', [EventParticipantController::class, 'store'])->whereNumber('event')->name('participants.store');
        Route::middleware('permission:events.invitations.send')->post('/{event}/participants/{participant}/pass', [EventPassController::class, 'issue'])->whereNumber('event')->whereNumber('participant')->name('participants.pass.issue');
        Route::middleware('permission:events.invitations.send')->get('/{event}/pass-deliveries', [EventPassController::class, 'deliveryStatus'])->whereNumber('event')->name('passes.delivery-status');
        Route::middleware(['permission:events.invitations.send', 'throttle:events-pass-retry'])->post('/{event}/passes/{pass}/retry-delivery', [EventPassController::class, 'retryDelivery'])->whereNumber('event')->whereNumber('pass')->name('passes.retry-delivery');
        Route::middleware('permission:events.participants.no_show')->post('/{event}/participants/{participant}/no-show', [EventParticipantController::class, 'noShow'])->whereNumber('event')->whereNumber('participant')->name('participants.no-show');
        Route::middleware('permission:events.invitations.send')->post('/{event}/participants/{participant}/cancel', [EventParticipantController::class, 'cancel'])->whereNumber('event')->whereNumber('participant')->name('participants.cancel');
        Route::middleware('permission:events.invitations.send')->post('/{event}/passes/{pass}/revoke', [EventPassController::class, 'revoke'])->whereNumber('event')->whereNumber('pass')->name('passes.revoke');
        Route::middleware('permission:events.invitations.send')->post('/{event}/passes/{pass}/attendees', [EventPassAttendeeController::class, 'store'])->whereNumber('event')->whereNumber('pass')->name('passes.attendees.store');
    });


// Public registration intentionally omits authentication and organization.access.
// Organization context is still resolved from the organization host, and the
// Events module must be enabled for that organization.
Route::middleware(['organization', 'module:Events'])
    ->prefix('v1/public/events')
    ->name('events.public.')
    ->group(function (): void {
        Route::post('/{event}/registrations', [PublicEventRegistrationController::class, 'store'])
            ->whereNumber('event')
            ->middleware('throttle:events-public-registration')
            ->name('registrations.store');
    });

// Pass-token lookup is intended for the check-in workflow. It returns only minimum display details.
Route::middleware(['organization', 'module:Events'])
    ->prefix('v1/public/event-passes')
    ->name('events.public.passes.')
    ->group(function (): void {
        Route::post('/validate', [EventPassController::class, 'validateToken'])
            ->middleware('throttle:events-public-pass-validation')
            ->name('validate');
    });

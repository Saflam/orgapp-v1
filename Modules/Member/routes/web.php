<?php

use Illuminate\Support\Facades\Route;
use Modules\Member\Livewire\MembershipTypeManagement;
use Modules\Member\Livewire\MembershipApplicationManagement;
use Modules\Member\Livewire\MemberPaymentCategoryManagement;
use Modules\Member\Http\Controllers\Web\MembershipApplicationPageController;
use Modules\Member\Http\Controllers\Web\MembershipApplicationMediaController;
use Modules\Member\Http\Controllers\Api\MembershipApplicationController;

Route::middleware(['auth', 'organization', 'organization.access'])
    ->group(function (): void {
        Route::get('/organization/membership-types', MembershipTypeManagement::class)
            ->name('member.membership-types');
        Route::get('/organization/membership-applications', MembershipApplicationManagement::class)
            ->name('member.membership-applications');
        Route::get('/organization/membership-applications/{applicationId}/media/{mediaId}', [MembershipApplicationMediaController::class, 'show'])
            ->whereNumber('applicationId')
            ->whereNumber('mediaId')
            ->name('member.membership-application.media');
        Route::get('/organization/payment-categories', MemberPaymentCategoryManagement::class)
            ->name('member.payment-categories');
    });

// Organization-facing membership application pages and their browser endpoints.
Route::middleware(['organization', 'auth'])->group(function (): void {
    Route::get('/membership/apply', [MembershipApplicationPageController::class, 'show'])
        ->name('organization.membership.apply');

    Route::middleware(['module:Member'])->group(function (): void {
        Route::get('/membership/apply/configuration', [MembershipApplicationPageController::class, 'configuration'])
            ->name('organization.membership.apply.configuration');

        Route::get('/membership/apply/profile-photo', [MembershipApplicationPageController::class, 'profilePhoto'])
            ->name('organization.membership.apply.profile-photo');

        Route::get('/membership/apply/draft-documents/{mediaId}', [MembershipApplicationPageController::class, 'draftDocument'])
            ->whereNumber('mediaId')
            ->name('organization.membership.apply.draft-document');

        Route::post('/membership/apply/draft', [MembershipApplicationPageController::class, 'saveDraft'])
            ->name('organization.membership.apply.draft');

        Route::get('/membership/apply/spouse-search', [MembershipApplicationController::class, 'searchSpouseMembers'])
            ->name('organization.membership.apply.spouse-search');

        Route::get('/membership/apply/spouse', [MembershipApplicationPageController::class, 'showSpouse'])
            ->name('organization.membership.apply.spouse');

        Route::post('/membership/apply/spouse', [MembershipApplicationController::class, 'storeSpouse'])
            ->name('organization.membership.apply.spouse.submit');

        Route::post('/membership/apply', [MembershipApplicationController::class, 'store'])
            ->middleware(\Modules\Member\Http\Middleware\UseAuthenticatedApplicant::class)
            ->name('organization.membership.apply.submit');
    });
});

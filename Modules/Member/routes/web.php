<?php

use Illuminate\Support\Facades\Route;
use Modules\Member\Livewire\MembershipTypeManagement;
use Modules\Member\Livewire\MembershipApplicationManagement;
use Modules\Member\Livewire\MemberPaymentCategoryManagement;
use Modules\Member\Livewire\MemberDirectory;
use Modules\Member\Http\Controllers\Web\MembershipApplicationPageController;
use Modules\Member\Http\Controllers\Web\MembershipApplicationMediaController;
use Modules\Member\Http\Controllers\Api\MembershipApplicationController;
use Modules\Member\Http\Controllers\Web\MemberPortalController;
use Modules\Member\Http\Controllers\Web\AdminMemberProfileController;
use Modules\Member\Http\Controllers\Web\MemberPaymentManagementController;
use Modules\Member\Http\Controllers\Web\SpouseRelationshipRequestController;

Route::middleware(['auth', 'organization', 'organization.access'])
    ->group(function (): void {
        Route::get('/organization/membership-types', MembershipTypeManagement::class)
            ->name('member.membership-types');
        Route::get('/organization/membership-applications', MembershipApplicationManagement::class)
            ->name('member.membership-applications');
        Route::get('/organization/members', MemberDirectory::class)
            ->middleware('module:Member')
            ->name('member.directory');
        Route::get('/organization/members/{member}', [AdminMemberProfileController::class, 'show'])
            ->whereNumber('member')
            ->middleware('module:Member')
            ->name('member.profile');
        Route::get('/organization/members/{member}/pdf', [AdminMemberProfileController::class, 'downloadPdf'])
            ->whereNumber('member')
            ->middleware('module:Member')
            ->name('member.profile.pdf');
        Route::get('/organization/members/{member}/edit', [AdminMemberProfileController::class, 'edit'])
            ->whereNumber('member')
            ->middleware('module:Member')
            ->name('member.profile.edit');
        Route::put('/organization/members/{member}', [AdminMemberProfileController::class, 'update'])
            ->whereNumber('member')
            ->middleware('module:Member')
            ->name('member.profile.update');
        Route::post('/organization/members/{member}/notes', [AdminMemberProfileController::class, 'storeNote'])
            ->whereNumber('member')
            ->middleware('module:Member')
            ->name('member.profile.notes.store');
        Route::get('/organization/membership-applications/{applicationId}/media/{mediaId}', [MembershipApplicationMediaController::class, 'show'])
            ->whereNumber('applicationId')
            ->whereNumber('mediaId')
            ->name('member.membership-application.media');
        Route::get('/organization/payment-categories', MemberPaymentCategoryManagement::class)
            ->name('member.payment-categories');
        Route::get('/organization/member-payments', [MemberPaymentManagementController::class, 'index'])
            ->middleware('module:Member')
            ->name('member.payments.index');
        Route::post('/organization/member-payments/members/{member}/collect', [MemberPaymentManagementController::class, 'collect'])
            ->whereNumber('member')
            ->middleware('module:Member')
            ->name('member.payments.collect');
    });

// Member self-service portal. Keep these tenant- and module-scoped in the Member module.
Route::middleware(['auth', 'organization', 'organization.access', 'module:Member'])
    ->group(function (): void {
        Route::get('/member/profile', [MemberPortalController::class, 'show'])
            ->name('organization.member.profile');
        Route::post('/member/profile/spouse-requests', [SpouseRelationshipRequestController::class, 'store'])
            ->name('organization.member.spouse-requests.store');
        Route::post('/member/profile/spouse-requests/{spouseRequest}/cancel', [SpouseRelationshipRequestController::class, 'cancel'])
            ->whereNumber('spouseRequest')
            ->name('organization.member.spouse-requests.cancel');
        Route::post('/member/profile/spouse-requests/{spouseRequest}/respond', [SpouseRelationshipRequestController::class, 'respond'])
            ->whereNumber('spouseRequest')
            ->name('organization.member.spouse-requests.respond');
        Route::post('/member/profile/spouses/{memberId}/disconnect', [SpouseRelationshipRequestController::class, 'disconnect'])
            ->whereNumber('memberId')
            ->name('organization.member.spouses.disconnect');
        Route::get('/member/id-card', [MemberPortalController::class, 'idCard'])
            ->name('organization.member.id-card');

        Route::middleware('permission:members.view')->group(function (): void {
            Route::get('/member/id-card/verify/{member}', [MemberPortalController::class, 'verifyIdCard'])
                ->whereNumber('member')
                ->middleware('signed')
                ->name('organization.member.id-card.verify');

            Route::get('/member/id-card/verify/{member}/dependants/{dependant}', [MemberPortalController::class, 'verifyDependantIdCard'])
                ->whereNumber('member')
                ->whereNumber('dependant')
                ->middleware('signed')
                ->name('organization.member.dependants.id-card.verify');
        });
        Route::put('/member/profile', [MemberPortalController::class, 'update'])
            ->name('organization.member.profile.update');
        Route::put('/member/profile/modules/{module}', [MemberPortalController::class, 'updateModuleProfile'])
            ->where('module', '[A-Za-z0-9_-]+')
            ->name('organization.member.profile.module.update');
        Route::post('/member/profile/photo', [MemberPortalController::class, 'updatePhoto'])
            ->name('organization.member.profile.photo');
        Route::get('/member/profile/photo', [MemberPortalController::class, 'profilePhoto'])
            ->name('organization.member.profile.photo.show');
        Route::post('/member/profile/dependants', [MemberPortalController::class, 'storeDependant'])
            ->name('organization.member.dependants.store');
        Route::put('/member/profile/dependants/{dependant}', [MemberPortalController::class, 'updateDependant'])
            ->whereNumber('dependant')
            ->name('organization.member.dependants.update');
        Route::delete('/member/profile/dependants/{dependant}', [MemberPortalController::class, 'destroyDependant'])
            ->whereNumber('dependant')
            ->name('organization.member.dependants.destroy');
        Route::post('/member/profile/dependants/{dependant}/documents', [MemberPortalController::class, 'storeDependantDocument'])
            ->whereNumber('dependant')
            ->name('organization.member.dependants.documents.store');
        Route::put('/member/profile/dependants/{dependant}/documents/{identification}', [MemberPortalController::class, 'updateDependantDocument'])
            ->whereNumber('dependant')->whereNumber('identification')
            ->name('organization.member.dependants.documents.update');
        Route::delete('/member/profile/dependants/{dependant}/documents/{identification}', [MemberPortalController::class, 'destroyDependantDocument'])
            ->whereNumber('dependant')->whereNumber('identification')
            ->name('organization.member.dependants.documents.destroy');
        Route::get('/member/profile/dependants/{dependant}/documents/{identification}/{side}', [MemberPortalController::class, 'dependantDocument'])
            ->whereNumber('dependant')->whereNumber('identification')->whereIn('side', ['document', 'front', 'back'])
            ->name('organization.member.dependants.document');
        Route::get('/member/profile/dependants/{dependant}/photo', [MemberPortalController::class, 'dependantPhoto'])
            ->whereNumber('dependant')
            ->name('organization.member.dependants.photo');
        Route::get('/member/profile/dependants/{dependant}/id-card', [MemberPortalController::class, 'dependantIdCard'])
            ->whereNumber('dependant')
            ->name('organization.member.dependants.id-card');
        Route::put('/member/profile/documents/{identification}', [MemberPortalController::class, 'updateProfileDocument'])
    ->whereNumber('identification')
    ->name('organization.member.profile.document.update');
Route::get('/member/profile/documents/{identification}/{side}', [MemberPortalController::class, 'document'])
            ->whereNumber('identification')
            ->whereIn('side', ['document', 'front', 'back'])
            ->name('organization.member.profile.document');
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

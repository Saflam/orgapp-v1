<?php

use Illuminate\Support\Facades\Route;
use Modules\Committee\Livewire\CommitteeDashboard;
use Modules\Committee\Livewire\CommitteeIndex;
use Modules\Committee\Livewire\CommitteeTermManagement;
use Modules\Committee\Livewire\CommitteeMemberManagement;
use Modules\Committee\Http\Controllers\CommitteeMemberPhotoController;
use Modules\Committee\Livewire\DesignationIndex;
use Modules\Committee\Livewire\DesignationEdit;
use Modules\Committee\Livewire\CurrentCommitteeList;
use Modules\Committee\Livewire\CommitteeMembershipHistory;
use Modules\Committee\Livewire\CommitteeMemberProfile;

Route::middleware(['auth', 'organization', 'organization.access'])->group(function (): void {
    Route::get('/organization/committee/current', CurrentCommitteeList::class)->name('committee.current');
    Route::get('/organization/committee', CommitteeDashboard::class)->name('committee.dashboard');
    Route::get('/organization/committee/committees', CommitteeIndex::class)->name('committee.committees');
    Route::get('/organization/committee/committees/{committee}/terms', CommitteeTermManagement::class)->whereNumber('committee')->name('committee.terms');
    Route::get('/organization/committee/terms/{term}/members', CommitteeMemberManagement::class)->whereNumber('term')->name('committee.members');
    Route::get('/organization/committee/terms/{term}/membership-history', CommitteeMembershipHistory::class)->whereNumber('term')->name('committee.membership-history');
    Route::get('/organization/committee/members/{member}/profile', CommitteeMemberProfile::class)->whereNumber('member')->name('committee.member-profile');
    Route::get('/organization/committee/members/{member}/profile-photo', [CommitteeMemberPhotoController::class, 'show'])->whereNumber('member')->name('committee.member-photo');
    Route::get('/organization/committee/designations', DesignationIndex::class)->name('committee.designations');
    Route::get('/organization/committee/designations/{designation}/edit', DesignationEdit::class)->whereNumber('designation')->name('committee.designations.edit');
});

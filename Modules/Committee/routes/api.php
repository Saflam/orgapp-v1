<?php

use Illuminate\Support\Facades\Route;
use Modules\Committee\Http\Controllers\Api\CommitteeController;
use Modules\Committee\Http\Controllers\Api\CommitteeMembershipController;
use Modules\Committee\Http\Controllers\Api\DesignationController;

Route::prefix('v1')->group(function () {
    Route::get('organizations/{organization}/committees',[CommitteeController::class, 'index'])->name('committee.index');
    Route::get('organizations/{organization}/committees/{committee}', [CommitteeController::class, 'show'])->name('committee.show');
    Route::post('organizations/{organization}/committees', [CommitteeController::class, 'store'])->name('committee.store');
    Route::put('organizations/{organization}/committees/{committee}',[CommitteeController::class, 'update'])->name('committee.update');
    Route::post('organizations/{organization}/committees/{committee}/archive', [CommitteeController::class, 'archive'])->name('committee.archive');
    
    Route::post('organizations/{organization}/committees/{committee}/terms', [CommitteeController::class, 'storeTerm'])->name('committee.term.store');
    Route::get('organizations/{organization}/committees/{committee}/terms', [CommitteeController::class, 'indexTerms'])->name('committee.term.index');
    Route::put('organizations/{organization}/committees/{committee}/terms/{term}', [CommitteeController::class, 'updateTerm'])->name('committee.term.update');
    Route::post('organizations/{organization}/committees/{committee}/terms/{term}/activate',[CommitteeController::class, 'activateTerm'])->name('committee.term.activate');
    Route::post('organizations/{organization}/committees/{committee}/terms/{term}/complete', [CommitteeController::class, 'completeTerm'])->name('committee.term.complete');

    Route::get('organizations/{organization}/designations',[DesignationController::class, 'index'])->name('designation.index');
    Route::post('organizations/{organization}/designations', [DesignationController::class, 'store'])->name('designation.store');
    Route::get('organizations/{organization}/designations/{designation}', [DesignationController::class, 'show'])->name('designation.show');
    Route::put('organizations/{organization}/designations/{designation}',[DesignationController::class, 'update'])->name('designation.update');

    Route::post('organizations/{organization}/designations/{designation}/activate', [DesignationController::class, 'activate'])->name('designation.activate');
    Route::post('organizations/{organization}/designations/{designation}/deactivate', [DesignationController::class, 'deactivate'])->name('designation.deactivate');

    Route::get('organizations/{organization}/committee-terms/{committeeTerm}/memberships', [CommitteeMembershipController::class, 'index'])->name('committee-membership.index');
    Route::post('organizations/{organization}/committee-terms/{committeeTerm}/memberships', [CommitteeMembershipController::class, 'store'])->name('committee-membership.store');

    Route::post('organizations/{organization}/committee-memberships/{membership}/end', [CommitteeMembershipController::class, 'end'])->name('committee-membership.end');
    Route::post('organizations/{organization}/committee-memberships/{membership}/cancel', [CommitteeMembershipController::class, 'cancel'])->name('committee-membership.cancel');
    Route::patch('organizations/{organization}/committee-memberships/{membership}', [CommitteeMembershipController::class, 'update'])->name('committee-membership.update');

    Route::post('organizations/{organization}/committee-memberships/{membership}/change-designation',[CommitteeMembershipController::class, 'changeDesignation'])->name('committee-membership.change-designation');

    Route::get('organizations/{organization}/committee-terms/{committeeTerm}/memberships/effective', [CommitteeMembershipController::class, 'effective'])->name('committee-membership.effective');
});
<?php

use Illuminate\Support\Facades\Route;
use Modules\Member\Http\Controllers\Api\MemberController;
use Modules\Member\Http\Controllers\Api\MemberDependantController;
use Modules\Member\Http\Controllers\Api\MemberDependantRelationshipController;
use Modules\Member\Http\Controllers\Api\MemberRelationshipController;
use Modules\Member\Http\Controllers\Api\MembershipApplicationController;

Route::middleware([
    'auth',
    'organization',
    'organization.access',
    'module:Member',
])->prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Membership Applications Routes
    |--------------------------------------------------------------------------
    */

    Route::get(
        'membership-application',
        [MembershipApplicationController::class, 'configuration']
    )->name('membership-application.configuration');

    Route::post(
        'membership-application',
        [MembershipApplicationController::class, 'store']
    )->name('membership-application.store');
    
    /*
    |--------------------------------------------------------------------------
    | Member Profile Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:members.profile.view')->group(function () {

        Route::get(
            'organizations/{organization}/members/me',
            [MemberController::class, 'profile']
        )->name('member.profile');
    });

    Route::middleware('permission:members.profile.update')->group(function () {

        Route::put(
            'organizations/{organization}/members/me',
            [MemberController::class, 'updateProfile']
        )->name('member.profile.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Member / Dependant / Relationship Read Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:members.view')->group(function () {

        Route::get(
            'organizations/{organization}/members',
            [MemberController::class, 'index']
        )->name('member.index');

        Route::get(
            'organizations/{organization}/members/{member}',
            [MemberController::class, 'show']
        )->name('member.show');

        Route::get(
            'organizations/{organization}/members/{member}/dependants',
            [MemberDependantController::class, 'index']
        )->name('member-dependant.index');

        Route::get(
            'organizations/{organization}/members/{member}/dependants/{dependant}',
            [MemberDependantController::class, 'show']
        )->name('member-dependant.show');

        Route::get(
            'organizations/{organization}/members/{member}/dependant-relationships',
            [MemberDependantRelationshipController::class, 'index']
        )->name('member-dependant-relationship.index');

        Route::get(
            'organizations/{organization}/members/{member}/dependant-relationships/{relationship}',
            [MemberDependantRelationshipController::class, 'show']
        )->name('member-dependant-relationship.show');

        Route::get(
            'organizations/{organization}/members/{member}/relationships',
            [MemberRelationshipController::class, 'index']
        )->name('member-relationship.index');
    });

    /*
    |--------------------------------------------------------------------------
    | Member / Dependant / Relationship Write Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:members.update')->group(function () {

        Route::post(
            'organizations/{organization}/members',
            [MemberController::class, 'store']
        )->name('member.store');

        Route::put(
            'organizations/{organization}/members/{member}',
            [MemberController::class, 'update']
        )->name('member.update');

        Route::post(
            'organizations/{organization}/members/{member}/dependants',
            [MemberDependantController::class, 'store']
        )->name('member-dependant.store');

        Route::put(
            'organizations/{organization}/members/{member}/dependants/{dependant}',
            [MemberDependantController::class, 'update']
        )->name('member-dependant.update');

        Route::post(
            'organizations/{organization}/members/{member}/dependants/{dependant}/convert',
            [MemberDependantController::class, 'convert']
        )->name('member-dependant.convert');

        Route::post(
            'organizations/{organization}/members/{member}/dependant-relationships',
            [MemberDependantRelationshipController::class, 'store']
        )->name('member-dependant-relationship.store');

        Route::put(
            'organizations/{organization}/members/{member}/dependant-relationships/{relationship}',
            [MemberDependantRelationshipController::class, 'update']
        )->name('member-dependant-relationship.update');

        Route::post(
            'organizations/{organization}/members/{member}/dependant-relationships/{relationship}/end',
            [MemberDependantRelationshipController::class, 'end']
        )->name('member-dependant-relationship.end');

        Route::post(
            'organizations/{organization}/members/{member}/relationships',
            [MemberRelationshipController::class, 'store']
        )->name('member-relationship.store');

        Route::put(
            'organizations/{organization}/members/{member}/relationships/{relationship}',
            [MemberRelationshipController::class, 'update']
        )->name('member-relationship.update');

        Route::post(
            'organizations/{organization}/members/{member}/relationships/{relationship}/end',
            [MemberRelationshipController::class, 'end']
        )->name('member-relationship.end');
    });
});
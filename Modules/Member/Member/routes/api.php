<?php

use Illuminate\Support\Facades\Route;
use Modules\Member\Http\Controllers\Api\MemberController;
use Modules\Member\Http\Controllers\Api\MemberDependantController;
use Modules\Member\Http\Controllers\Api\MemberDependantRelationshipController;
use Modules\Member\Http\Controllers\Api\MemberRelationshipController;
use Modules\Member\Http\Controllers\Api\MembershipApplicationController;
use Modules\Member\Http\Controllers\Api\MemberPaymentItemController;
use Modules\Member\Http\Controllers\Api\MemberPaymentController;
use Modules\Member\Http\Controllers\Api\MemberUnitTransferController;

Route::middleware([
    'auth',
    'organization',
    'organization.access',
    'module:Member',
])->prefix('v1')->group(function () {

    /* Unit transfer workflow */
    Route::prefix('organizations/{organization}/member-unit-transfers')->name('member-unit-transfer.')->group(function () {
        Route::get('/', [MemberUnitTransferController::class, 'index'])->name('index');
        Route::post('/', [MemberUnitTransferController::class, 'store'])->name('store');
        Route::get('/{transfer}', [MemberUnitTransferController::class, 'show'])->name('show');
        Route::post('/{transfer}/approvals/{side}/approve', [MemberUnitTransferController::class, 'approve'])->name('approve');
        Route::post('/{transfer}/reject', [MemberUnitTransferController::class, 'reject'])->name('reject');
        Route::post('/{transfer}/complete', [MemberUnitTransferController::class, 'complete'])->name('complete');
    });

    /*
    |--------------------------------------------------------------------------
    | Membership Applications Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:membership.application.submit')->group(function () {
        Route::get(
            'membership-application',
            [MembershipApplicationController::class, 'configuration']
        )->name('membership-application.configuration');

        Route::post(
            'membership-application',
            [MembershipApplicationController::class, 'store']
        )->name('membership-application.store');
    });

    Route::middleware('permission:membership.application.view')->group(function () {
        Route::get(
            'membership-applications/{membershipApplication}',
            [MembershipApplicationController::class, 'show']
        )->name('membership-application.show');
    });

    Route::middleware('permission:membership.application.edit')->group(function () {
        Route::put(
            'membership-applications/{membershipApplication}',
            [MembershipApplicationController::class, 'update']
        )->name('membership-application.update');
    });

    Route::middleware('permission:membership.application.verify')->group(function () {
        Route::post(
            'membership-applications/{membershipApplication}/verify',
            [MembershipApplicationController::class, 'verify']
        )->name('membership-application.verify');
    });

    Route::middleware('permission:membership.application.review')->group(function () {
        Route::post(
            'membership-applications/{membershipApplication}/review',
            [MembershipApplicationController::class, 'review']
        )->name('membership-application.review');
    });

    Route::middleware('permission:membership.application.approve')->group(function () {
        Route::post(
            'membership-applications/{membershipApplication}/approve',
            [MembershipApplicationController::class, 'approve']
        )->name('membership-application.approve');
    });

    Route::middleware('permission:membership.payment.receive')->group(function () {
        Route::post(
            'membership-applications/{membershipApplication}/payment',
            [MembershipApplicationController::class, 'receivePayment']
        )->name('membership-application.payment');
    });

    Route::middleware('permission:membership.application.confirm')->group(function () {
        Route::get(
            'membership-applications/{membershipApplication}/membership-number-suggestion',
            [MembershipApplicationController::class, 'membershipNumberSuggestion']
        )->name('membership-application.membership-number-suggestion');

        Route::post(
            'membership-applications/{membershipApplication}/confirm',
            [MembershipApplicationController::class, 'confirm']
        )->name('membership-application.confirm');
    });

    /*
    |--------------------------------------------------------------------------
    | Membership Charge Configuration Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:membership.payment-item.view')->group(function () {
        Route::get(
            'member-payment-items',
            [MemberPaymentItemController::class, 'index']
        )->name('member-payment-item.index');
    });

    Route::middleware('permission:membership.payment-item.manage')->group(function () {
        Route::post(
            'member-payment-items',
            [MemberPaymentItemController::class, 'store']
        )->name('member-payment-item.store');

        Route::put(
            'member-payment-items/{memberPaymentItem}',
            [MemberPaymentItemController::class, 'update']
        )->name('member-payment-item.update');

        Route::post(
            'member-payment-items/{memberPaymentItem}/rates',
            [MemberPaymentItemController::class, 'addRate']
        )->name('member-payment-item.rate.store');
    });

    /*
    |--------------------------------------------------------------------------
    | Payment Collection Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:membership.payment.view')->group(function () {
        Route::get(
            'membership-payments/applications/pending',
            [MemberPaymentController::class, 'pendingApplications']
        )->name('membership-payment.applications.pending');

        Route::get(
            'membership-payments/recurring/pending',
            [MemberPaymentController::class, 'pendingRecurring']
        )->name('membership-payment.recurring.pending');

        Route::get(
            'membership-payments/members/{member}/pending',
            [MemberPaymentController::class, 'pendingMember']
        )->name('membership-payment.member.pending');

        Route::get(
            'units/{unit}/member-payments/members/{member}/pending',
            [MemberPaymentController::class, 'pendingMemberInUnit']
        )->name('member-payment.unit.member.pending');

        Route::get(
            'membership-payments/application/{application}',
            [MemberPaymentController::class, 'pendingApplication']
        )->name('membership-payment.application.pending');
    });

    Route::middleware('permission:membership.payment.discount')->group(function () {
        Route::post(
            'membership-payments/{obligation}/discount',
            [MemberPaymentController::class, 'discount']
        )->name('membership-payment.discount');
    });

    Route::middleware('permission:membership.payment.receive')->group(function () {
        Route::post(
            'membership-payments/{obligation}/paid',
            [MemberPaymentController::class, 'markPaid']
        )->name('membership-payment.paid');

        Route::post(
            'units/{unit}/member-payments/{obligation}/paid',
            [MemberPaymentController::class, 'markPaidInUnit']
        )->name('member-payment.unit.paid');
    });

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

        Route::delete(
            'organizations/{organization}/members/{member}/dependants/{dependant}',
            [MemberDependantController::class, 'destroy']
        )->name('member-dependant.destroy');

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
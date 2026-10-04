<?php

use Illuminate\Support\Facades\Route;

Route::middleware([
    'organization',
    'organization.access',
])->group(function () {
    Route::get('/dashboard', function () {
        return response()->json([
            'organization_id' => app(\App\Support\Organization\CurrentOrganization::class)->get()->id,
            'organization_slug' => app(\App\Support\Organization\CurrentOrganization::class)->get()->slug,
        ]);
    })->name('organization.dashboard');
});
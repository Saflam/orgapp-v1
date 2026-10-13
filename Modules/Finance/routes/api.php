<?php

use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    'organization',
    'organization.access',
    'module:Finance',
])->prefix('v1')->group(function () {
    // Finance APIs will be added after the accounting foundation is established.
});

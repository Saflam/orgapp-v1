
<?php

use App\Support\Organization\CurrentOrganization;
use Illuminate\Support\Facades\Route;

Route::middleware(['organization'])->group(function () {
    Route::get('/site', function (CurrentOrganization $currentOrganization) {
        $organization = $currentOrganization->get();
        $branding = data_get($organization->settings, 'branding', []);

        $primaryColor = data_get($branding, 'primary_color', '#174b3d');
        $accentColor = data_get($branding, 'accent_color', '#d9a441');

        if (! is_string($primaryColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) {
            $primaryColor = '#174b3d';
        }

        if (! is_string($accentColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
            $accentColor = '#d9a441';
        }

        // Accept only local public asset paths for organization branding.
        $logoPath = data_get($branding, 'logo_path');
        if (! is_string($logoPath) || ! preg_match('/^\/storage\/[a-zA-Z0-9_\/.-]+$/', $logoPath)
            || str_contains($logoPath, '..')) {
            $logoPath = null;
        }

        $faviconPath = data_get($branding, 'favicon_path');
        if (! is_string($faviconPath) || ! preg_match('/^\/storage\/[a-zA-Z0-9_\/.-]+$/', $faviconPath)
            || str_contains($faviconPath, '..')) {
            $faviconPath = '/favicon.ico';
        }

        return response()
            ->view('organization.site', [
                'organization' => $organization,
                'branding' => $branding,
                'primaryColor' => $primaryColor,
                'accentColor' => $accentColor,
                'logoPath' => $logoPath,
                'faviconPath' => $faviconPath,
            ])
            ->header('Cache-Control', 'private, no-store');
    })->name('organization.site');

    Route::get('/organization.webmanifest', function (CurrentOrganization $currentOrganization) {
        $organization = $currentOrganization->get();
        $branding = data_get($organization->settings, 'branding', []);

        $themeColor = data_get($branding, 'primary_color', '#174b3d');

        if (! is_string($themeColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
            $themeColor = '#174b3d';
        }

        $faviconPath = data_get($branding, 'favicon_path');

        if (! is_string($faviconPath)
            || ! preg_match('/^\/storage\/[a-zA-Z0-9_\/.-]+$/', $faviconPath)
            || str_contains($faviconPath, '..')) {
            $faviconPath = '/favicon.ico';
        }

        return response()->json([
            'id' => '/site',
            'name' => $organization->name,
            'short_name' => mb_substr($organization->name, 0, 18),
            'description' => 'Official website and services for ' . $organization->name,
            'start_url' => '/site',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#fafaf8',
            'theme_color' => $themeColor,
            'icons' => [
                [
                    'src' => $faviconPath,
                    'sizes' => 'any',
                ],
            ],
        ])
            ->header('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->header('Cache-Control', 'private, no-store');
    })->name('organization.manifest');

    Route::middleware(['organization.access'])->group(function () {
        Route::get('/dashboard', function (CurrentOrganization $currentOrganization) {
            $organization = $currentOrganization->get();

            return response()->json([
                'organization_id' => $organization->id,
                'organization_slug' => $organization->slug,
            ]);
        })->name('organization.dashboard');
    });
});

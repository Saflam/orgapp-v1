<?php

use App\Support\Organization\CurrentOrganization;
use App\Http\Controllers\Auth\OrganizationRegistrationController;
use Modules\Member\Http\Controllers\Api\MembershipApplicationController;
use Modules\Member\Http\Controllers\Web\MembershipApplicationPageController;
use Modules\Member\Http\Middleware\UseAuthenticatedApplicant;
use Modules\Member\Models\MembershipApplication;
use Modules\Member\Enums\MembershipApplicationStatus;
use App\Models\OrganizationMembership;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use Illuminate\Support\Facades\Route;

Route::middleware(['organization'])->group(function () {
    Route::get('/home', function (CurrentOrganization $currentOrganization) {
        $organization = $currentOrganization->get();
        $branding = data_get($organization->settings, 'branding', []);

        $primaryColor = data_get($branding, 'primary_color', '#174b3d');
        $accentColor = data_get($branding, 'accent_color', '#d9a441');

        // Validate values before rendering them into inline CSS.
        if (! is_string($primaryColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) {
            $primaryColor = '#174b3d';
        }

        if (! is_string($accentColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
            $accentColor = '#d9a441';
        }

        // Branding assets must be public storage paths, never arbitrary URLs or filesystem paths.
        $validateAssetPath = static function (mixed $path): ?string {
            if (! is_string($path)
                || ! preg_match('~^/storage/[a-zA-Z0-9_./-]+$~', $path)
                || str_contains($path, '..')) {
                return null;
            }

            return $path;
        };

        $logoPath = $validateAssetPath(data_get($branding, 'logo_path'));
        $faviconPath = $validateAssetPath(data_get($branding, 'favicon_path')) ?? '/favicon.ico';

        $existingApplication = auth()->check() ? MembershipApplication::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first() : null;
        $hasPendingMembershipApplication = $existingApplication !== null
            && ! ($existingApplication->status === MembershipApplicationStatus::REJECTED && $existingApplication->latestRejectionAllowsReapply());
        $hasDraftMembershipApplication = $existingApplication?->status === MembershipApplicationStatus::DRAFT;

        return response()
            ->view('organization.site', [
                'organization' => $organization,
                'branding' => $branding,
                'primaryColor' => $primaryColor,
                'accentColor' => $accentColor,
                'logoPath' => $logoPath,
                'faviconPath' => $faviconPath,
                'hasPendingMembershipApplication' => $hasPendingMembershipApplication,
                'hasDraftMembershipApplication' => $hasDraftMembershipApplication,
                'isMember' => auth()->check() && OrganizationMembership::query()
                    ->where('organization_id', $organization->id)
                    ->where('user_id', auth()->id())
                    ->where('status', 'active')
                    ->exists(),
                'canManageOrganization' => auth()->check() && (
                    auth()->user()->is_system_admin || app(AuthorizationService::class)->can(
                        auth()->user(),
                        'organization.settings.manage',
                        new AuthorizationContext($organization->id),
                    )
                ),
                'eventsModuleEnabled' => \Illuminate\Support\Facades\DB::table('organization_modules')
                    ->where('organization_id', $organization->id)
                    ->where('module', 'Events')
                    ->where('is_enabled', true)
                    ->exists(),
            ])
            ->header('Cache-Control', 'private, no-store');
    })->name('organization.home');

    // Keep old bookmarks working while making /home the canonical route.
    Route::get('/site', fn () => redirect()->route('organization.home', [], 301))->name('organization.site');

    Route::get('/organization.webmanifest', function (CurrentOrganization $currentOrganization) {
        $organization = $currentOrganization->get();
        $branding = data_get($organization->settings, 'branding', []);

        $themeColor = data_get($branding, 'primary_color', '#174b3d');
        if (! is_string($themeColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
            $themeColor = '#174b3d';
        }

        $faviconPath = data_get($branding, 'favicon_path');
        if (! is_string($faviconPath)
            || ! preg_match('~^/storage/[a-zA-Z0-9_./-]+$~', $faviconPath)
            || str_contains($faviconPath, '..')) {
            $faviconPath = '/favicon.ico';
        }

        return response()->json([
            'id' => '/home',
            'name' => $organization->name,
            'short_name' => mb_substr($organization->name, 0, 18),
            'description' => 'Official website and services for ' . $organization->name,
            'start_url' => '/home',
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

    // A global Saflivo User is registered once; organization access is granted for this tenant only.
    Route::middleware('guest')->group(function () {
        Route::get('/register', [OrganizationRegistrationController::class, 'show'])
            ->name('organization.register');
        Route::post('/register', [OrganizationRegistrationController::class, 'register'])
            ->name('organization.register.store');
        Route::get('/register/verify', [OrganizationRegistrationController::class, 'showVerify'])
            ->name('organization.register.verify.form');
        Route::post('/register/verify', [OrganizationRegistrationController::class, 'verify'])
            ->name('organization.register.verify');
    });

    // The general account profile requires authentication, but not organization
    // membership: non-members still need access to their account and the
    // organization-specific membership application entry point.
    Route::middleware('auth')->group(function () {
        Route::get('/profile', function (CurrentOrganization $currentOrganization) {
            $organization = $currentOrganization->get();
            $branding = data_get($organization->settings, 'branding', []);
            $primaryColor = data_get($branding, 'primary_color', '#174b3d');

            if (! is_string($primaryColor) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) {
                $primaryColor = '#174b3d';
            }

            $logoPath = data_get($branding, 'logo_path');
            if (! is_string($logoPath)
                || ! preg_match('~^/storage/[a-zA-Z0-9_./-]+$~', $logoPath)
                || str_contains($logoPath, '..')) {
                $logoPath = null;
            }

            return response()->view('organization.profile', [
                'organization' => $organization,
                'user' => auth()->user(),
                'authenticatedUser' => auth()->user(),
                'primaryColor' => $primaryColor,
                'logoPath' => $logoPath,
                'isMember' => auth()->user()->isMember($organization),
                'hasPendingMembershipApplication' => (function () use ($organization): bool {
                    $application = MembershipApplication::query()->where('organization_id', $organization->id)->where('user_id', auth()->id())->latest('id')->first();
                    return $application !== null && ! ($application->status === MembershipApplicationStatus::REJECTED && $application->latestRejectionAllowsReapply());
                })(),
                'hasDraftMembershipApplication' => MembershipApplication::query()
                    ->where('organization_id', $organization->id)
                    ->where('user_id', auth()->id())
                    ->where('status', MembershipApplicationStatus::DRAFT)
                    ->exists(),
            ])->header('Cache-Control', 'private, no-store');
        })->name('organization.profile');

        Route::get('/membership/apply', function (CurrentOrganization $currentOrganization) {
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

            $existingApplication = MembershipApplication::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', auth()->id())
                ->latest('id')->first();
            if ($existingApplication && $existingApplication->status !== MembershipApplicationStatus::DRAFT && ! ($existingApplication->status === MembershipApplicationStatus::REJECTED && $existingApplication->latestRejectionAllowsReapply())) {
                return response()->view('organization.membership-application-status', [
                    'organization' => $organization,
                    'application' => $existingApplication,
                    'primaryColor' => $primaryColor,
                ])->header('Cache-Control', 'private, no-store');
            }

            return response()->view('organization.membership-apply', [
                'organization' => $organization,
                'primaryColor' => $primaryColor,
                'accentColor' => $accentColor,
                'authenticatedUser' => auth()->user(),
            ])->header('Cache-Control', 'private, no-store');
        })->name('organization.membership.apply');

        // Self-service applicants are not necessarily assigned an organization role yet.
        // Authentication, tenant resolution, module availability, and the forced user_id
        // middleware still apply to the browser submission endpoints.
        Route::middleware(['module:Member'])->group(function (): void {
            Route::get('/membership/apply/configuration', [MembershipApplicationPageController::class, 'configuration'])
                ->name('organization.membership.apply.configuration');
            Route::post('/membership/apply/draft', [MembershipApplicationPageController::class, 'saveDraft'])
                ->name('organization.membership.apply.draft');

            Route::get('/membership/apply/spouse-search', [MembershipApplicationController::class, 'searchSpouseMembers'])
                ->name('organization.membership.apply.spouse-search');

            Route::get('/membership/apply/spouse', function (\Illuminate\Http\Request $request, CurrentOrganization $currentOrganization) {
                $organization = $currentOrganization->get();
                $source = MembershipApplication::query()
                    ->whereKey((int) $request->query('source_application'))
                    ->where('organization_id', $organization->id)
                    ->where('user_id', auth()->id())
                    ->firstOrFail();
                $branding = data_get($organization->settings, 'branding', []);
                $primaryColor = data_get($branding, 'primary_color', '#174b3d');
                $accentColor = data_get($branding, 'accent_color', '#d9a441');
                return response()->view('organization.membership-apply', [
                    'organization' => $organization,
                    'primaryColor' => is_string($primaryColor) && preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor) ? $primaryColor : '#174b3d',
                    'accentColor' => is_string($accentColor) && preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor) ? $accentColor : '#d9a441',
                    'authenticatedUser' => auth()->user(),
                    'spouseMode' => true,
                    'spousePrefill' => $source->data['addresses'] ?? [],
                ])->header('Cache-Control', 'private, no-store');
            })->name('organization.membership.apply.spouse');

            Route::post('/membership/apply/spouse', [MembershipApplicationController::class, 'storeSpouse'])
                ->name('organization.membership.apply.spouse.submit');

            Route::post('/membership/apply', [MembershipApplicationController::class, 'store'])
                ->middleware(UseAuthenticatedApplicant::class)
                ->name('organization.membership.apply.submit');
        });
    });

    // Member-only profile and dashboard endpoints require active organization access.
    // EnsureOrganizationAccess returns 401 for guests and 403 for another org's user.
    Route::middleware(['auth', 'organization.access'])->group(function () {
        Route::get('/member/profile', function (CurrentOrganization $currentOrganization) {
            return response()->view('organization.member-profile', [
                'organization' => $currentOrganization->get(),
                'user' => auth()->user(),
            ]);
        })->name('organization.member.profile');

    });

    // Dashboard access uses its own middleware, which returns 401 for guests
    // and 403 when an authenticated user belongs to a different organization.
    Route::middleware('organization.access')->get('/dashboard', function (CurrentOrganization $currentOrganization) {
        $organization = $currentOrganization->get();

        return response()->json([
            'organization_id' => $organization->id,
            'organization_slug' => $organization->slug,
        ]);
    })->name('organization.dashboard');
});

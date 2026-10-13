<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use Modules\Events\Services\EventManagementService;
use Modules\Events\Services\EventReportService;
use Modules\Events\Models\Event;

class EventReportController extends Controller
{
    public function __construct(
        private readonly EventManagementService $events,
        private readonly EventReportService $reports,
    ) {
    }

    public function summary(Request $request, int $event): JsonResponse
    {
        $organization = $this->organization();
        $this->assertUserHasAnyReportPermission($request, $organization);
        $record = $this->events->queryVisibleTo($organization, $request->user(), 'events.reports.view')->findOrFail($event);

        return response()->json([
            'data' => $this->reports->summary($organization, $record),
        ]);
    }

    public function organizationSummary(Request $request): JsonResponse
    {
        $organization = $this->organization();

        $filters = $this->visibleFilters($request, $organization);

        return response()->json([
            'data' => $this->reports->organizationSummary($organization, $filters),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $organization = $this->organization();

        $filters = $this->visibleFilters($request, $organization);

        return response()->json([
            'data' => $this->reports->categorySummary($organization, $filters),
        ]);
    }


    public function registrations(Request $request, int $event): JsonResponse
    {
        $organization = $this->organization();
        $this->assertUserHasAnyReportPermission($request, $organization);
        $record = $this->events->queryVisibleTo($organization, $request->user(), 'events.reports.view')->findOrFail($event);
        $filters = $this->validatedDetailFilters($request, [
            'status' => ['sometimes', 'nullable', Rule::in(['invited', 'registered', 'cancelled', 'no_show'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'data' => $this->reports->registrations($organization, $record, $filters),
        ]);
    }

    public function attendance(Request $request, int $event): JsonResponse
    {
        $organization = $this->organization();
        $this->assertUserHasAnyReportPermission($request, $organization);
        $record = $this->events->queryVisibleTo($organization, $request->user(), 'events.reports.view')->findOrFail($event);
        $filters = $this->validatedDetailFilters($request, [
            'action' => ['sometimes', 'nullable', Rule::in(['entry', 'exit'])],
        ]);

        return response()->json([
            'data' => $this->reports->attendance($organization, $record, $filters),
        ]);
    }

    public function passes(Request $request, int $event): JsonResponse
    {
        $organization = $this->organization();
        $this->assertUserHasAnyReportPermission($request, $organization);
        $record = $this->events->queryVisibleTo($organization, $request->user(), 'events.reports.view')->findOrFail($event);
        $filters = $this->validatedDetailFilters($request, [
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'revoked', 'expired'])],
        ]);

        return response()->json([
            'data' => $this->reports->passes($organization, $record, $filters),
        ]);
    }

    private function validatedDetailFilters(Request $request, array $extraRules): array
    {
        return $request->validate(array_merge([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ], $extraRules));
    }


    /**
     * Preserve the API distinction between a user who has no report permission
     * at all (403) and a user who has report permission for some scope but
     * cannot see this particular event (404 from queryVisibleTo()->findOrFail()).
     */
    private function assertUserHasAnyReportPermission(Request $request, Organization $organization): void
    {
        $authorization = app(AuthorizationService::class);
        $user = $request->user();

        if ($authorization->can(
            $user,
            'events.reports.view',
            new AuthorizationContext((int) $organization->id),
        )) {
            return;
        }

        $hasAnyUnitPermission = Unit::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get(['id'])
            ->contains(fn (Unit $unit): bool => $authorization->can(
                $user,
                'events.reports.view',
                new AuthorizationContext((int) $organization->id, (int) $unit->id),
            ));

        if (! $hasAnyUnitPermission) {
            abort(403, 'You do not have permission to view event reports.');
        }
    }

    private function visibleFilters(Request $request, Organization $organization): array
    {
        $this->assertCanViewOrganizationReports($request, $organization);

        $filters = $this->validatedFilters($request, $organization);
        $filters['_visible_event_ids'] = $this->events
            ->queryVisibleTo($organization, $request->user(), 'events.reports.view')
            ->pluck('id')
            ->all();

        return $filters;
    }


    /**
     * Collection reports have no event route parameter from which the permission
     * middleware can infer a unit context. Allow a user with report permission
     * at the organization level or at least one active unit, then constrain the
     * actual report to events visible under those same assignments.
     */
    private function assertCanViewOrganizationReports(Request $request, Organization $organization): void
    {
        $authorization = app(AuthorizationService::class);
        $user = $request->user();
        $organizationContext = new AuthorizationContext((int) $organization->id);

        if ($authorization->can($user, 'events.reports.view', $organizationContext)) {
            return;
        }

        $hasUnitPermission = Unit::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get(['id'])
            ->contains(fn (Unit $unit): bool => $authorization->can(
                $user,
                'events.reports.view',
                new AuthorizationContext((int) $organization->id, (int) $unit->id),
            ));

        if (! $hasUnitPermission) {
            abort(403, 'You do not have permission to view event reports.');
        }
    }

    private function validatedFilters(Request $request, Organization $organization): array
    {
        return $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'unit_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('organization_id', $organization->id)),
            ],
            'category_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('event_categories', 'id')->where(fn ($query) => $query->where('organization_id', $organization->id)),
            ],
            'status' => ['sometimes', 'nullable', Rule::in(['draft', 'published', 'cancelled'])],
        ]);
    }

    private function organization(): Organization
    {
        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages([
                'organization' => 'The selected organization is invalid.',
            ]);
        }

        return $organization;
    }
}

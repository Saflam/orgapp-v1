<?php

namespace Modules\Committee\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Committee\Http\Requests\StoreCommitteeRequest;
use Modules\Committee\Http\Requests\StoreCommitteeTermRequest;
use Modules\Committee\Http\Requests\UpdateCommitteeRequest;
use Modules\Committee\Http\Requests\UpdateCommitteeTermRequest;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Services\CommitteeService;
use Modules\Committee\Transformers\CommitteeResource;
use Modules\Committee\Transformers\CommitteeTermResource;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;

class CommitteeController extends Controller
{
    public function index(
        Organization $organization,
        OrganizationModuleService $moduleService,
    ): JsonResource {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        $committees = Committee::query()
            ->where('organization_id', $organization->id)
            ->get();

        return CommitteeResource::collection($committees);
    }

    public function show(
        Organization $organization,
        Committee $committee,
        OrganizationModuleService $moduleService,
    ): CommitteeResource {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        abort_unless(
            $committee->organization_id === $organization->id,
            404,
            'Committee not found.'
        );

        return new CommitteeResource($committee);
    }

    public function store(
        StoreCommitteeRequest $request,
        Organization $organization,
        CommitteeService $committeeService,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        $committee = $committeeService->createRecurringCommittee(
            organizationId: $organization->id,
            committeeTypeId: $request->integer('committee_type_id'),
            name: $request->string('name')->toString(),
            unitId: $request->input('unit_id'),
            parentCommitteeId: $request->input('parent_committee_id'),
        );

        return (new CommitteeResource($committee))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCommitteeRequest $request,
        Organization $organization,
        Committee $committee,
        CommitteeService $committeeService,
        OrganizationModuleService $moduleService,
    ): CommitteeResource {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        abort_unless(
            $committee->organization_id === $organization->id,
            404,
            'Committee not found.'
        );

        $committee = $committeeService->updateCommittee(
            committee: $committee,
            name: $request->string('name')->toString(),
            unitId: $request->input('unit_id'),
            parentCommitteeId: $request->input('parent_committee_id'),
        );

        return new CommitteeResource($committee);
    }

    public function archive(
        Organization $organization,
        Committee $committee,
        CommitteeService $committeeService,
        OrganizationModuleService $moduleService,
    ): CommitteeResource {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        abort_unless(
            $committee->organization_id === $organization->id,
            404,
            'Committee not found.'
        );

        $committee = $committeeService->archiveCommittee($committee);

        return new CommitteeResource($committee);
    }

    public function storeTerm(
        StoreCommitteeTermRequest $request,
        Organization $organization,
        Committee $committee,
        CommitteeService $committeeService,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        abort_unless(
            $committee->organization_id === $organization->id,
            404,
            'Committee not found.'
        );

        $term = $committeeService->createTerm(
            committee: $committee,
            startDate: $request->date('start_date')->format('Y-m-d'),
            endDate: $request->date('end_date')->format('Y-m-d'),
        );

        return (new CommitteeTermResource($term))
            ->response()
            ->setStatusCode(201);
    }

    public function indexTerms(
        Organization $organization,
        Committee $committee,
        OrganizationModuleService $moduleService,
    ): JsonResource {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        abort_unless(
            $committee->organization_id === $organization->id,
            404,
            'Committee not found.'
        );

        $terms = CommitteeTerm::query()
            ->where('committee_id', $committee->id)
            ->orderBy('start_date')
            ->get();

        return CommitteeTermResource::collection($terms);
    }

    public function activateTerm(
        Organization $organization,
        Committee $committee,
        CommitteeTerm $term,
        CommitteeService $committeeService,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        abort_unless(
            $committee->organization_id === $organization->id,
            404,
            'Committee not found.'
        );

        abort_unless(
            $term->committee_id === $committee->id,
            404,
            'Committee term not found.'
        );

        $term = $committeeService->activateTerm($term);

        return (new CommitteeTermResource($term))
            ->response()
            ->setStatusCode(200);
    }

    public function completeTerm(
        Organization $organization,
        Committee $committee,
        CommitteeTerm $term,
        CommitteeService $committeeService,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        abort_unless(
            $committee->organization_id === $organization->id,
            404,
            'Committee not found.'
        );

        abort_unless(
            $term->committee_id === $committee->id,
            404,
            'Committee term not found.'
        );

        $term = $committeeService->completeTerm($term);

        return (new CommitteeTermResource($term))
            ->response()
            ->setStatusCode(200);
    }

    public function updateTerm(
        UpdateCommitteeTermRequest $request,
        Organization $organization,
        Committee $committee,
        CommitteeTerm $term,
        CommitteeService $committeeService,
        OrganizationModuleService $moduleService,
    ): JsonResponse {
        $this->ensureCommitteeModuleEnabled($organization, $moduleService);

        abort_unless(
            $committee->organization_id === $organization->id,
            404,
            'Committee not found.'
        );

        abort_unless(
            $term->committee_id === $committee->id,
            404,
            'Committee term not found.'
        );

        $term = $committeeService->updateTerm(
            term: $term,
            startDate: $request->date('start_date')->format('Y-m-d'),
            endDate: $request->date('end_date')->format('Y-m-d'),
        );

        return (new CommitteeTermResource($term))
            ->response()
            ->setStatusCode(200);
    }

    private function ensureCommitteeModuleEnabled(
        Organization $organization,
        OrganizationModuleService $moduleService,
    ): void {
        if (! $moduleService->isEnabled(
            $organization,
            'Committee',
        )) {
            abort(
                403,
                'The Committee module is not enabled for this organization.'
            );
        }
    }
}
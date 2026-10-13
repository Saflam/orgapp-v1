<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Enums\MemberUnitTransferApprovalSide;
use Modules\Member\Http\Requests\RejectMemberUnitTransferRequest;
use Modules\Member\Http\Requests\StoreMemberUnitTransferRequest;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberUnitTransfer;
use Modules\Member\Services\MemberUnitTransferService;

class MemberUnitTransferController extends Controller
{
    public function __construct(
        private readonly MemberUnitTransferService $transferService,
        private readonly AuthorizationService $authorization,
        private readonly OrganizationModuleService $moduleService,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->ensureMemberModuleEnabled($organization);
        $this->ensureHasAnyUnitPermission($request->user(), $organization, 'member.unit-transfer.view');

        $transfers = MemberUnitTransfer::query()
            ->where('organization_id', $organization->id)
            ->with(['member', 'spouse', 'fromUnit', 'toUnit', 'approvals.unit', 'approvals.decidedBy'])
            ->latest('id')
            ->get()
            ->filter(fn (MemberUnitTransfer $transfer): bool =>
                $this->canViewTransfer($request->user(), $transfer)
            )
            ->values();

        return response()->json(['data' => $transfers]);
    }

    public function show(Request $request, Organization $organization, int $transfer): JsonResponse
    {
        $this->ensureMemberModuleEnabled($organization);
        $model = $this->findTransfer($organization, $transfer);
        abort_unless($this->canViewTransfer($request->user(), $model), 403, 'You are not authorized to view this unit transfer.');

        $model->load(['member', 'spouse', 'fromUnit', 'toUnit', 'approvals.unit', 'approvals.decidedBy']);

        return response()->json(['data' => $model]);
    }

    public function store(StoreMemberUnitTransferRequest $request, Organization $organization): JsonResponse
    {
        $this->ensureMemberModuleEnabled($organization);
        $member = Member::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($request->integer('member_id'));

        abort_if($member->unit_id === null, 422, 'A member without a current unit cannot request a unit transfer.');
        abort_unless(
            $this->authorization->can(
                $request->user(),
                'member.unit-transfer.submit',
                new AuthorizationContext($organization->id, $member->unit_id),
            ),
            403,
            'You are not authorized to submit unit transfers for this unit.'
        );

        try {
            $transfer = $this->transferService->submit(
                member: $member,
                targetUnitId: $request->integer('to_unit_id'),
                spouseIncluded: $request->has('spouse_included') ? $request->boolean('spouse_included') : null,
            );
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }

        $transfer->load(['member', 'spouse', 'fromUnit', 'toUnit', 'approvals.unit']);

        return response()->json(['data' => $transfer], 201);
    }

    public function approve(Request $request, Organization $organization, int $transfer, string $side): JsonResponse
    {
        $this->ensureMemberModuleEnabled($organization);
        $model = $this->findTransfer($organization, $transfer);
        $approvalSide = $this->parseSide($side);

        try {
            $result = $this->transferService->approve($model, $request->user(), $approvalSide);
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }

        $result->load(['member', 'spouse', 'fromUnit', 'toUnit', 'approvals.unit', 'approvals.decidedBy']);

        return response()->json(['data' => $result]);
    }

    public function reject(RejectMemberUnitTransferRequest $request, Organization $organization, int $transfer): JsonResponse
    {
        $this->ensureMemberModuleEnabled($organization);
        $model = $this->findTransfer($organization, $transfer);
        $side = $this->parseSide($request->string('side')->toString());

        try {
            $result = $this->transferService->reject(
                transfer: $model,
                actor: $request->user(),
                side: $side,
                reason: $request->string('reason')->toString(),
            );
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }

        $result->load(['member', 'spouse', 'fromUnit', 'toUnit', 'approvals.unit', 'approvals.decidedBy']);

        return response()->json(['data' => $result]);
    }

    public function complete(Request $request, Organization $organization, int $transfer): JsonResponse
    {
        $this->ensureMemberModuleEnabled($organization);
        $model = $this->findTransfer($organization, $transfer);

        try {
            $result = $this->transferService->complete($model, $request->user());
        } catch (DomainException $exception) {
            return $this->domainError($exception);
        }

        $result->load(['member', 'spouse', 'fromUnit', 'toUnit', 'approvals.unit', 'approvals.decidedBy']);

        return response()->json(['data' => $result]);
    }

    private function findTransfer(Organization $organization, int $id): MemberUnitTransfer
    {
        return MemberUnitTransfer::query()
            ->where('organization_id', $organization->id)
            ->findOrFail($id);
    }

    private function parseSide(string $side): MemberUnitTransferApprovalSide
    {
        $parsed = MemberUnitTransferApprovalSide::tryFrom($side);
        abort_if($parsed === null, 422, 'The approval side must be outgoing or incoming.');

        return $parsed;
    }

    private function canViewTransfer(User $user, MemberUnitTransfer $transfer): bool
    {
        return $this->authorization->can(
            $user,
            'member.unit-transfer.view',
            new AuthorizationContext($transfer->organization_id, $transfer->from_unit_id),
        ) || $this->authorization->can(
            $user,
            'member.unit-transfer.view',
            new AuthorizationContext($transfer->organization_id, $transfer->to_unit_id),
        );
    }

    private function ensureHasAnyUnitPermission(User $user, Organization $organization, string $permission): void
    {
        if ($this->authorization->can($user, $permission, new AuthorizationContext($organization->id))) {
            return;
        }

        $unitIds = Unit::query()->where('organization_id', $organization->id)->pluck('id');
        foreach ($unitIds as $unitId) {
            if ($this->authorization->can($user, $permission, new AuthorizationContext($organization->id, (int) $unitId))) {
                return;
            }
        }

        abort(403, 'You are not authorized to view unit transfers in this organization.');
    }

    private function ensureMemberModuleEnabled(Organization $organization): void
    {
        abort_unless($this->moduleService->isEnabled($organization, 'Member'), 403, 'The Member module is not enabled for this organization.');
    }

    private function domainError(DomainException $exception): JsonResponse
    {
        $message = $exception->getMessage();
        $status = str_contains(strtolower($message), 'not authorized') ? 403 : 422;

        return response()->json(['message' => $message], $status);
    }
}

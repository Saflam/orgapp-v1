<?php

namespace Modules\Member\Http\Controllers\Web;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Enums\MemberPaymentObligationStatus;
use Modules\Member\Models\Member;
use Modules\Member\Models\MemberPayment;
use Modules\Member\Models\MemberPaymentObligation;
use Modules\Member\Services\MemberPaymentService;

class MemberPaymentManagementController extends Controller
{
    public function index(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        MemberPaymentService $payments,
    ): Response {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization, 404);

        $this->ensureOrganizationPermission($authorization, $organization, 'membership.payment.view');

        $search = trim((string) $request->query('search', ''));
        $obligations = $payments->pendingRecurringObligations((int) $organization->id);
        $obligations->each(fn (MemberPaymentObligation $obligation) => $obligation->loadMissing('member.unit'));
        $obligations = $obligations->filter(function (MemberPaymentObligation $obligation) use ($authorization, $organization, $search): bool {
                $member = $obligation->member;
                if (! $member || ! $this->canAccessMember($authorization, $organization, $member, 'membership.payment.view')) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $name = (string) ($member->user?->name ?? '');
                $number = (string) ($member->membership_number ?? '');
                $item = (string) ($obligation->item_title ?? '');

                return str_contains(mb_strtolower($name), mb_strtolower($search))
                    || str_contains(mb_strtolower($number), mb_strtolower($search))
                    || str_contains(mb_strtolower($item), mb_strtolower($search));
            })
            ->values();

        $obligationsByMember = $obligations
            ->groupBy('member_id')
            ->map(fn ($rows) => $rows->sortBy(fn ($row) => $row->due_at?->format('Y-m-d') ?? '9999-12-31')->values());

        $paymentsHistory = MemberPayment::query()
            ->with(['member.user', 'member.unit', 'receivedBy', 'lines.obligation'])
            ->where('organization_id', $organization->id)
            ->whereNotNull('member_id')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('receipt_number', 'like', '%'.$search.'%')
                        ->orWhere('reference', 'like', '%'.$search.'%')
                        ->orWhereHas('member', function ($memberQuery) use ($search): void {
                            $memberQuery->where('membership_number', 'like', '%'.$search.'%')
                                ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%'));
                        });
                });
            })
            ->latest('received_at')
            ->limit(100)
            ->get()
            ->filter(fn (MemberPayment $payment): bool =>
                $payment->member !== null
                && $this->canAccessMember($authorization, $organization, $payment->member, 'membership.payment.view')
            )
            ->values();

        return response()
            ->view('member::organization.member-payment-management', compact(
                'organization', 'search', 'obligationsByMember', 'paymentsHistory'
            ))
            ->header('Cache-Control', 'private, no-store');
    }

    public function collect(
        Request $request,
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        MemberPaymentService $payments,
    ): RedirectResponse {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization, 404);

        $memberRecord = Member::query()
            ->where('organization_id', $organization->id)
            ->with('user')
            ->findOrFail($member);

        abort_unless($this->canAccessMember($authorization, $organization, $memberRecord, 'membership.payment.receive'), 403);
        abort_unless($this->canAccessMember($authorization, $organization, $memberRecord, 'membership.payment.view'), 403);

        $validated = $request->validate([
            'obligation_ids' => ['required', 'array', 'min:1'],
            'obligation_ids.*' => ['required', 'integer', 'distinct'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,card,cheque,online,other'],
            'reference' => ['nullable', 'string', 'max:150'],
        ]);

        $obligations = MemberPaymentObligation::query()
            ->with('member')
            ->where('organization_id', $organization->id)
            ->where('member_id', $memberRecord->id)
            ->whereIn('id', $validated['obligation_ids'])
            ->whereIn('status', [
                MemberPaymentObligationStatus::PENDING,
                MemberPaymentObligationStatus::PARTIALLY_PAID,
            ])
            ->get();

        if ($obligations->count() !== count($validated['obligation_ids'])) {
            return back()->withErrors([
                'obligation_ids' => 'One or more selected dues are no longer payable or do not belong to this member.',
            ])->withInput();
        }

        $unitId = $memberRecord->unit_id;
        $organizationContext = new AuthorizationContext((int) $organization->id);
        $isOrganizationCollector = $authorization->can(
            $request->user(),
            'membership.payment.receive',
            $organizationContext,
        );

        $payment = $payments->recordPayment(
            $obligations,
            $request->user(),
            $isOrganizationCollector ? 'organization' : 'unit',
            $isOrganizationCollector ? (int) $organization->id : $unitId,
            $validated['payment_method'],
            $validated['reference'] ?? null,
            ['source' => 'organization_member_payment_page'],
        );

        return redirect()
            ->route('member.payments.index', ['search' => $memberRecord->membership_number ?: $memberRecord->user?->name])
            ->with('status', 'Payment recorded. Receipt number: '.$payment->receipt_number);
    }

    private function ensureOrganizationPermission(
        AuthorizationService $authorization,
        Organization $organization,
        string $permission,
    ): void {
        $user = auth()->user();
        abort_unless($user, 403);

        // The route permission middleware confirms the permission exists; this check
        // allows either organization-wide authority or a unit-scoped authority.
        $hasOrganizationAccess = $authorization->can(
            $user,
            $permission,
            new AuthorizationContext((int) $organization->id),
        );

        abort_unless($hasOrganizationAccess || $this->hasAnyAuthorizedUnit(
            $authorization,
            $organization,
            $permission,
        ), 403);
    }

    private function hasAnyAuthorizedUnit(
        AuthorizationService $authorization,
        Organization $organization,
        string $permission,
    ): bool {
        $user = auth()->user();

        return \Modules\Core\Models\Unit::query()
            ->where('organization_id', $organization->id)
            ->get(['id'])
            ->contains(fn ($unit): bool => $authorization->can(
                $user,
                $permission,
                new AuthorizationContext((int) $organization->id, (int) $unit->id),
            ));
    }

    private function canAccessMember(
        AuthorizationService $authorization,
        Organization $organization,
        Member $member,
        string $permission,
    ): bool {
        $user = auth()->user();

        if ($authorization->can($user, $permission, new AuthorizationContext((int) $organization->id))) {
            return true;
        }

        return $member->unit_id !== null && $authorization->can(
            $user,
            $permission,
            new AuthorizationContext((int) $organization->id, (int) $member->unit_id),
        );
    }
}

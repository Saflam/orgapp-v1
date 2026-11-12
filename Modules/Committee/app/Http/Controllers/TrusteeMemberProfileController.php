<?php

namespace Modules\Committee\Http\Controllers;

use App\Support\Organization\CurrentOrganization;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\Committee\Models\TrusteeAppointment;
use Modules\Committee\Services\TrusteeService;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Models\Member;

class TrusteeMemberProfileController extends Controller
{
    public function appoint(
        Request $request,
        int $member,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        TrusteeService $trustees,
    ): RedirectResponse {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization, 404);
        $memberRecord = Member::query()->where('organization_id', $organization->id)->findOrFail($member);
        abort_unless($authorization->can($request->user(), 'trustee.manage', new AuthorizationContext((int) $organization->id)), 403);

        $validated = $request->validate([
            'trustee_id' => ['required', 'integer', 'min:1'],
            'appointed_at' => ['required', 'date'],
        ]);

        try {
            $trustees->appoint(
                $memberRecord,
                (int) $validated['trustee_id'],
                Carbon::parse($validated['appointed_at']),
                (int) $request->user()->id,
            );
        } catch (DomainException $exception) {
            return redirect()->route('member.profile', $memberRecord->id)
                ->withErrors(['trustee' => $exception->getMessage()]);
        }

        return redirect()->route('member.profile', $memberRecord->id)
            ->with('status', 'Trustee appointment saved successfully.');
    }

    public function remove(
        Request $request,
        int $member,
        int $appointment,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        TrusteeService $trustees,
    ): RedirectResponse {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization, 404);
        $memberRecord = Member::query()->where('organization_id', $organization->id)->findOrFail($member);
        abort_unless($authorization->can($request->user(), 'trustee.manage', new AuthorizationContext((int) $organization->id)), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'ended_at' => ['required', 'date'],
        ]);

        $appointmentRecord = TrusteeAppointment::query()
            ->where('member_id', $memberRecord->id)
            ->whereHas('trustee', fn ($query) => $query->where('organization_id', $organization->id))
            ->findOrFail($appointment);

        try {
            $trustees->remove(
                $appointmentRecord,
                $validated['reason'],
                (int) $request->user()->id,
                Carbon::parse($validated['ended_at']),
            );
        } catch (DomainException $exception) {
            return redirect()->route('member.profile', $memberRecord->id)
                ->withErrors(['trustee' => $exception->getMessage()]);
        }

        return redirect()->route('member.profile', $memberRecord->id)
            ->with('status', 'Trustee appointment removed. The trustee identity and appointment history have been retained.');
    }
}

<?php

namespace Modules\Committee\Http\Controllers;

use App\Support\Organization\CurrentOrganization;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Committee\Enums\TrusteeAppointmentStatus;
use Modules\Committee\Models\Trustee;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrusteeDirectoryController
{
    public function active(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ): View {
        $organization = $this->authorizedOrganization($currentOrganization, $authorization, $modules);
        $trustees = $this->query($organization, 'active', trim((string) $request->query('search', '')))
            ->paginate(20)
            ->withQueryString();

        return view('committee::organization.trustees.active', compact('organization', 'trustees'))
            ->with('search', trim((string) $request->query('search', '')));
    }

    public function history(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ): View {
        $organization = $this->authorizedOrganization($currentOrganization, $authorization, $modules);
        $search = trim((string) $request->query('search', ''));
        $historyStatus = (string) $request->query('status', 'all');
        abort_unless(in_array($historyStatus, ['all', 'removed', 'inactive'], true), 404);

        $trustees = $this->query($organization, 'history', $search, $historyStatus)
            ->paginate(20)
            ->withQueryString();

        return view('committee::organization.trustees.history', compact('organization', 'trustees', 'search', 'historyStatus'));
    }

    public function exportActivePdf(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ): StreamedResponse {
        $organization = $this->authorizedOrganization($currentOrganization, $authorization, $modules);
        $trustees = $this->query($organization, 'active', trim((string) $request->query('search', '')))->get();

        return $this->pdfResponse($trustees, $organization, 'Active Trustees', 'active-trustees');
    }

    public function exportHistoryPdf(
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ): StreamedResponse {
        $organization = $this->authorizedOrganization($currentOrganization, $authorization, $modules);
        $search = trim((string) $request->query('search', ''));
        $historyStatus = (string) $request->query('status', 'all');
        abort_unless(in_array($historyStatus, ['all', 'removed', 'inactive'], true), 404);
        $trustees = $this->query($organization, 'history', $search, $historyStatus)->get();

        $title = match ($historyStatus) {
            'removed' => 'Former Trustees',
            'inactive' => 'Inactive Trustees',
            default => 'Former and Inactive Trustees',
        };

        return $this->pdfResponse($trustees, $organization, $title, 'trustee-history');
    }

    private function authorizedOrganization(
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ): Organization {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization, 404);
        abort_unless($modules->isEnabled($organization, 'Committee'), 404);

        $user = auth()->user();
        $context = new AuthorizationContext((int) $organization->id);
        abort_unless($user && (
            $authorization->can($user, 'trustee.view', $context)
            || $authorization->can($user, 'trustee.manage', $context)
        ), 403);

        return $organization;
    }

    private function query(Organization $organization, string $section, string $search, string $historyStatus = 'all'): Builder
    {
        $query = Trustee::query()
            ->where('organization_id', $organization->id)
            ->with([
                'member.user',
                'member.unit',
                'appointments' => fn ($appointments) => $appointments->orderByDesc('appointed_at')->orderByDesc('id'),
            ]);

        if ($section === 'active') {
            $query->whereHas('appointments', fn (Builder $appointments) => $appointments
                ->where('status', TrusteeAppointmentStatus::ACTIVE->value));
        } else {
            // Classify each trustee by the latest appointment only. This avoids
            // an old inactive period affecting a later reappointment's status.
            $query->whereHas('appointments', function (Builder $appointments) use ($historyStatus): void {
                $appointments->whereIn('status', match ($historyStatus) {
                    'removed' => [TrusteeAppointmentStatus::REMOVED->value],
                    'inactive' => [TrusteeAppointmentStatus::INACTIVE->value],
                    default => [TrusteeAppointmentStatus::REMOVED->value, TrusteeAppointmentStatus::INACTIVE->value],
                })->where('id', function ($latestAppointment): void {
                    $latestAppointment->select('id')
                        ->from('trustee_appointments as latest_trustee_appointments')
                        ->whereColumn('latest_trustee_appointments.trustee_id', 'trustees.id')
                        ->orderByDesc('latest_trustee_appointments.appointed_at')
                        ->orderByDesc('latest_trustee_appointments.id')
                        ->limit(1);
                });
            });
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                if (ctype_digit($search)) {
                    $builder->where('trustee_id', (int) $search);
                }
                $builder->orWhereHas('member', function (Builder $memberQuery) use ($search): void {
                    $memberQuery->where('membership_number', 'like', '%' . $search . '%')
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%'));
                });
            });
        }

        return $query->orderBy('trustee_id');
    }

    private function pdfResponse($trustees, Organization $organization, string $title, string $filename): StreamedResponse
    {
        $pdf = Pdf::loadView('committee::organization.trustees.pdf', compact('trustees', 'organization', 'title'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename . '-' . now()->format('Ymd-His') . '.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}

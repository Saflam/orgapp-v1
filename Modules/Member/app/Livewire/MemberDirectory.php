<?php

namespace Modules\Member\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Core\Services\AuthorizationService;
use Modules\Member\Models\Member;

class MemberDirectory extends Component
{
    use WithPagination;

    public string $searchInput = '';
    public string $search = '';
    public string $unitId = '';
    public string $status = '';

    protected string $paginationTheme = 'tailwind';

    public function mount(CurrentOrganization $currentOrganization): void
    {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization, 404);
        $this->authorizeList($organization);
    }

    public function updatedSearchInput(): void
    {
        $this->search = trim($this->searchInput);
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->search = trim($this->searchInput);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->searchInput = '';
        $this->search = '';
        $this->unitId = '';
        $this->status = '';
        $this->resetPage();
    }

    public function updatedUnitId(): void
    {
        $this->resetPage();
        $this->authorizeSelectedUnit();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    private function organization(): Organization
    {
        $organization = app(CurrentOrganization::class)->get();
        abort_unless($organization instanceof Organization, 404);
        return $organization;
    }

    private function authorizeList(?Organization $organization = null): void
    {
        $organization ??= $this->organization();
        $user = auth()->user();
        abort_unless($user, 403);
        $authorization = app(AuthorizationService::class);
        $orgContext = new AuthorizationContext((int) $organization->id);
        $canOrganizationView = $authorization->can($user, 'members.view', $orgContext)
            || $authorization->can($user, 'members.manage', $orgContext);

        if ($canOrganizationView) {
            return;
        }

        $hasUnitAccess = Unit::query()
            ->where('organization_id', $organization->id)
            ->get(['id'])
            ->contains(fn (Unit $unit): bool => $authorization->can(
                $user,
                'members.view',
                new AuthorizationContext((int) $organization->id, (int) $unit->id)
            ) || $authorization->can(
                $user,
                'members.manage',
                new AuthorizationContext((int) $organization->id, (int) $unit->id)
            ));

        abort_unless($hasUnitAccess, 403);
    }

    private function authorizedUnitIds(): array
    {
        $organization = $this->organization();
        $user = auth()->user();
        $authorization = app(AuthorizationService::class);
        $orgContext = new AuthorizationContext((int) $organization->id);

        if ($authorization->can($user, 'members.view', $orgContext)
            || $authorization->can($user, 'members.manage', $orgContext)) {
            return Unit::query()->where('organization_id', $organization->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return Unit::query()
            ->where('organization_id', $organization->id)
            ->get(['id'])
            ->filter(fn (Unit $unit): bool => $authorization->can(
                $user,
                'members.view',
                new AuthorizationContext((int) $organization->id, (int) $unit->id)
            ) || $authorization->can(
                $user,
                'members.manage',
                new AuthorizationContext((int) $organization->id, (int) $unit->id)
            ))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function authorizeSelectedUnit(): void
    {
        if ($this->unitId === '') {
            return;
        }

        abort_unless(in_array((int) $this->unitId, $this->authorizedUnitIds(), true), 403);
    }

    private function filteredMembersQuery(): Builder
    {
        $organization = $this->organization();
        $this->authorizeList($organization);
        $authorizedUnitIds = $this->authorizedUnitIds();
        $this->authorizeSelectedUnit();

        $query = Member::query()
            ->where('members.organization_id', $organization->id)
            ->whereIn('members.unit_id', $authorizedUnitIds)
            ->with(['user', 'unit', 'memberships.membershipType']);

        $search = trim($this->searchInput !== '' ? $this->searchInput : $this->search);
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('members.membership_number', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($this->unitId !== '') {
            $query->where('members.unit_id', (int) $this->unitId);
        }

        if ($this->status !== '') {
            $query->whereHas('memberships', fn (Builder $membershipQuery) => $membershipQuery->where('status', $this->status));
        }

        return $query->leftJoin('users as directory_users', 'directory_users.id', '=', 'members.user_id')
            ->select('members.*')
            ->orderBy('directory_users.name')
            ->orderBy('members.id');
    }

    public function exportExcel()
    {
        $rows = $this->filteredMembersQuery()->get();
        $filename = 'members-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            // UTF-8 BOM helps Excel open names and other non-ASCII text correctly.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Member', 'Email', 'Phone', 'Membership ID', 'Unit', 'Status']);
            foreach ($rows as $member) {
                $membership = $member->memberships->sortByDesc('starts_at')->first();
                $status = $membership?->status ?? 'unknown';
                if ($status instanceof \BackedEnum) $status = $status->value;
                fputcsv($output, [
                    $member->user?->name ?? 'Unnamed member',
                    $member->user?->email ?? '',
                    $member->user?->phone ?? '',
                    $member->membership_number ?? '',
                    $member->unit?->name ?? 'Organization-wide',
                    \Illuminate\Support\Str::headline((string) $status),
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf()
    {
        $members = $this->filteredMembersQuery()->get();
        $organization = $this->organization();
        $pdf = Pdf::loadView('member::livewire.member-directory-pdf', compact('members', 'organization'))
            ->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'members-' . now()->format('Ymd-His') . '.pdf',
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']
        );
    }

    public function render(): View
    {
        $organization = $this->organization();
        $this->authorizeList($organization);
        $authorizedUnitIds = $this->authorizedUnitIds();
        $this->authorizeSelectedUnit();

        $members = $this->filteredMembersQuery()->paginate(20);
        $units = Unit::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $authorizedUnitIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $statuses = [
            'active' => 'Active',
            'dormant' => 'Dormant',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
            'pending' => 'Pending',
        ];

        return view('member::livewire.member-directory', compact('members', 'units', 'statuses'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Members']);
    }
}

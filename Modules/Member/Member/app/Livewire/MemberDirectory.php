<?php

namespace Modules\Member\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
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

    public string $search = '';
    public string $unitId = '';
    public string $status = '';
    public string $sortDirection = 'asc';
    public string $sortBy = 'name';

    protected string $paginationTheme = 'tailwind';

    public function mount(CurrentOrganization $currentOrganization): void
    {
        $organization = $currentOrganization->get();
        abort_unless($organization instanceof Organization, 404);
        $this->authorizeList($organization);
    }

    public function updatedSearch(): void
    {
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

    public function setSortDirection(string $direction): void
    {
        abort_unless(in_array($direction, ['asc', 'desc'], true), 422);
        $this->sortDirection = $direction;
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

    public function render(): View
    {
        $organization = $this->organization();
        $this->authorizeList($organization);
        $authorizedUnitIds = $this->authorizedUnitIds();
        $this->authorizeSelectedUnit();

        $query = Member::query()
            ->where('organization_id', $organization->id)
            ->whereIn('unit_id', $authorizedUnitIds)
            ->with(['user', 'unit', 'memberships.membershipType']);

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('membership_number', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('phone', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($this->unitId !== '') {
            $query->where('unit_id', (int) $this->unitId);
        }

        if ($this->status !== '') {
            $query->whereHas('memberships', fn (Builder $membershipQuery) => $membershipQuery->where('status', $this->status));
        }

        // This screen intentionally sorts the filtered result set by one selected
        // field and direction rather than offering sortable column headers.
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        if ($this->sortBy === 'membership_number') {
            $query->orderBy('membership_number', $direction);
        } else {
            $query->leftJoin('users as directory_users', 'directory_users.id', '=', 'members.user_id')
                ->select('members.*')
                ->orderBy('directory_users.name', $direction);
        }

        $members = $query->orderBy('members.id')->paginate(25);

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

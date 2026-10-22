<?php

namespace Modules\Core\Livewire\Organizations;

use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationService;

class OrganizationIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function toggleStatus(int $organizationId, OrganizationService $service): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $organization = Organization::query()->findOrFail($organizationId);

        if ($organization->is_active) {
            $service->deactivate($organization);
        } else {
            $service->activate($organization);
        }

        session()->flash('success', 'Organization status updated.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $organizations = Organization::query()
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%' . trim($this->search) . '%';
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                });
            })
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->withCount(['modules as enabled_modules_count' => fn ($query) => $query->where('is_enabled', true)])
            ->orderBy('name')
            ->paginate(15);

        return view('core::livewire.organizations.index', compact('organizations'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Organizations']);
    }
}

<?php
namespace Modules\Core\Livewire\Organizations;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationManagementAccessService;

class OrganizationAdminDashboard extends Component
{
    public Organization $organization;

    public function mount(CurrentOrganization $currentOrganization, OrganizationManagementAccessService $access): void
    {
        $this->organization = $currentOrganization->get();
        abort_unless($access->canManageAnyModule(auth()->user(), $this->organization), 403);
    }

    public function render(OrganizationManagementAccessService $access): View
    {
        return view('core::livewire.organizations.admin-dashboard', [
            'manageableModules' => $access->manageableModules(auth()->user(), $this->organization),
            'canManageSettings' => $access->canManagePermission(
                auth()->user(), $this->organization, 'organization.settings.manage'
            ),
        ])->layout('core::layouts.organization-admin', [
            'pageTitle' => 'Organization dashboard',
            'layoutOrganization' => $this->organization,
        ]);
    }
}

<?php

namespace Modules\Core\Livewire\Organizations;

use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Services\ModuleRegistry;
use Modules\Core\Services\OrganizationService;

class OrganizationWizard extends Component
{
    public int $step = 1;

    public array $organization = [
        'name' => '',
        'code' => '',
        'timezone' => 'Asia/Kolkata',
        'currency' => 'INR',
    ];

    public array $super_admin = [
        'name' => '',
        'email' => '',
        'phone' => '',
    ];

    public array $notification_channels = [
        'sms' => ['use_system_default' => false],
        'whatsapp' => ['use_system_default' => false],
    ];

    /** @var array<int, string> */
    public array $availableModules = [];
    public array $modules = [];

    public function mount(ModuleRegistry $registry): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $this->availableModules = array_values(array_filter(
            $registry->enabledNames(),
            fn (string $module): bool => ! in_array($module, ['Core', 'Member'], true),
        ));
    }

    public function next(): void
    {
        $this->validateCurrentStep();
        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function create(OrganizationService $service)
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $this->validateCurrentStep();

        $validated = Validator::make([
            'organization' => $this->organization,
            'super_admin' => $this->super_admin,
            'notification_channels' => $this->notification_channels,
            'modules' => $this->modules,
        ], [
            'organization.name' => ['required', 'string', 'max:255'],
            'organization.code' => ['required', 'string', 'max:50', 'unique:organizations,code'],
            'organization.timezone' => ['required', 'timezone'],
            'organization.currency' => ['required', 'string', 'size:3'],
            'super_admin.name' => ['required', 'string', 'max:255'],
            'super_admin.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'super_admin.phone' => ['nullable', 'string', 'max:30'],
            'notification_channels.sms.use_system_default' => ['boolean'],
            'notification_channels.whatsapp.use_system_default' => ['boolean'],
            'modules' => ['array'],
            'modules.*' => ['string', 'max:100'],
        ])->validate();

        $organization = $service->create($validated);

        session()->flash('success', 'Organization created successfully.');

        return $this->redirectRoute('core.organizations.manage', ['organization' => $organization->id]);
    }

    private function validateCurrentStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'organization.name' => ['required', 'string', 'max:255'],
                'organization.code' => ['required', 'string', 'max:50', 'unique:organizations,code'],
                'organization.timezone' => ['required', 'timezone'],
                'organization.currency' => ['required', 'string', 'size:3'],
            ]);
        } elseif ($this->step === 2) {
            $this->validate([
                'super_admin.name' => ['required', 'string', 'max:255'],
                'super_admin.email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'super_admin.phone' => ['nullable', 'string', 'max:30'],
            ]);
        }
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        return view('core::livewire.organizations.wizard', ['availableModules' => $this->availableModules])
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Add organization']);
    }
}

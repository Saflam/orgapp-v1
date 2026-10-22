<?php

namespace Modules\Core\Livewire\Organizations;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Core\Models\Organization;
use Modules\Core\Services\NotificationService;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\OrganizationService;

class OrganizationManage extends Component
{
    use WithFileUploads;

    public Organization $organization;
    public string $name = '';
    public string $timezone = 'Asia/Kolkata';
    public string $currency = 'INR';
    public string $theme = 'system';
    public string $superAdminName = '';
    public string $superAdminEmail = '';
    public $logo = null;
    public $favicon = null;

    public function mount(Organization $organization): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $this->organization = $organization;
        $this->name = $organization->name;
        $this->timezone = $organization->timezone ?: 'Asia/Kolkata';
        $this->currency = $organization->currency ?: 'INR';
        $this->theme = (string) app(\Modules\Core\Services\OrganizationSettingsService::class)->get($organization, 'ui.theme', 'system');
        $this->loadSuperAdminFields();
    }


    public function saveOrganizationDetails(): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ]);

        $this->organization->update([
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'currency' => strtoupper($validated['currency']),
        ]);
        $this->organization->refresh();
        session()->flash('success', 'Organization details saved successfully.');
    }

    public function saveTheme(\Modules\Core\Services\OrganizationSettingsService $settings): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $validated = $this->validate([
            'theme' => ['required', 'in:light,dark,system'],
        ]);

        $settings->set($this->organization, 'ui.theme', $validated['theme']);
        session()->flash('success', 'Organization UI theme updated successfully.');
    }

    public function saveSuperAdminDetails(): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $superAdmin = $this->currentSuperAdmin();
        abort_if($superAdmin === null, 404, 'This organization has no active Super Administrator assigned.');

        $validated = $this->validate([
            'superAdminName' => ['required', 'string', 'max:255'],
            'superAdminEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($superAdmin->id)],
        ]);

        $superAdmin->update([
            'name' => $validated['superAdminName'],
            'email' => strtolower($validated['superAdminEmail']),
        ]);
        session()->flash('success', 'Organization Super Admin details saved successfully.');
    }

    public function saveBranding(\Modules\Core\Services\OrganizationSettingsService $settings): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $validated = $this->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:1024'],
        ]);

        foreach (['logo', 'favicon'] as $field) {
            if (! $this->{$field}) {
                continue;
            }

            $key = 'branding.' . $field;
            $oldPath = $settings->get($this->organization, $key);
            $path = $this->{$field}->storePublicly('organizations/' . $this->organization->id . '/branding', 'public');
            $settings->set($this->organization, $key, $path);

            if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path) {
                Storage::disk('public')->delete($oldPath);
            }

            $this->{$field} = null;
        }

        session()->flash('success', 'Organization branding saved successfully.');
    }

    private function loadSuperAdminFields(): void
    {
        $user = $this->currentSuperAdmin();
        $this->superAdminName = $user?->name ?? '';
        $this->superAdminEmail = $user?->email ?? '';
    }

    private function currentSuperAdmin(): ?User
    {
        $userId = DB::table('organization_memberships as om')
            ->join('role_assignments as ra', 'ra.organization_membership_id', '=', 'om.id')
            ->join('roles as r', 'r.id', '=', 'ra.role_id')
            ->where('om.organization_id', $this->organization->id)
            ->where('om.status', 'active')
            ->where('r.code', 'super_admin')
            ->where('ra.context_type', 'organization')
            ->where('ra.context_id', $this->organization->id)
            ->where(function ($query) { $query->whereNull('ra.starts_at')->orWhere('ra.starts_at', '<=', now()); })
            ->where(function ($query) { $query->whereNull('ra.ends_at')->orWhere('ra.ends_at', '>=', now()); })
            ->orderByDesc('ra.starts_at')
            ->value('om.user_id');

        return $userId ? User::query()->find($userId) : null;
    }

    public function toggleModule(string $module, OrganizationModuleService $service): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        if ($service->isEnabled($this->organization, $module)) {
            $service->disable($this->organization, $module);
        } else {
            $service->enable($this->organization, $module);
        }

        session()->flash('success', "Module {$module} updated.");
    }

    public function setSystemDefaultAccess(string $channel, bool $allowed, NotificationService $service): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        abort_unless(in_array($channel, ['sms', 'whatsapp'], true), 404);

        $service->setSystemDefaultAccess($this->organization, $channel, $allowed);
        session()->flash('success', ucfirst($channel) . ' system-default access updated.');
    }

    public function toggleOrganizationStatus(OrganizationService $service): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $this->organization->is_active
            ? $service->deactivate($this->organization)
            : $service->activate($this->organization);

        $this->organization->refresh();
        session()->flash('success', 'Organization status updated.');
    }

    public function render(OrganizationModuleService $moduleService): View
    {
        abort_unless(auth()->user()?->is_system_admin, 403);

        $modules = $moduleService->statuses($this->organization);
        $channels = $this->organization->notificationChannels()->get()->keyBy('channel');
        $settings = app(\Modules\Core\Services\OrganizationSettingsService::class);
        $logoPath = $settings->get($this->organization, 'branding.logo');
        $faviconPath = $settings->get($this->organization, 'branding.favicon');
        $logoUrl = $logoPath ? Storage::disk('public')->url($logoPath) : null;
        $faviconUrl = $faviconPath ? Storage::disk('public')->url($faviconPath) : null;
        $hasSuperAdmin = $this->currentSuperAdmin() !== null;

        return view('core::livewire.organizations.manage', compact('modules', 'channels', 'logoUrl', 'faviconUrl', 'hasSuperAdmin'))
            ->layout('core::layouts.organization-admin', [
                'pageTitle' => $this->organization->name,
                'layoutOrganization' => $this->organization,
            ]);
    }
}

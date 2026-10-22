<?php

namespace Modules\Core\Livewire\Organizations;

use App\Support\Organization\CurrentOrganization;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\AuthorizationService;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationSettingsService;

class OrganizationSelfSettings extends Component
{
    use WithFileUploads;

    public Organization $organization;
    public string $name = '';
    public string $timezone = 'Asia/Kolkata';
    public string $currency = 'INR';
    public string $theme = 'system';
    public string $successMessage = '';
    public $logo = null;
    public $favicon = null;
    public ?string $logoUrl = null;
    public ?string $faviconUrl = null;

    public function mount(CurrentOrganization $currentOrganization, OrganizationSettingsService $settings, AuthorizationService $authorization): void
    {
        $this->organization = $currentOrganization->get();
        $this->ensureCanManage($authorization);
        $this->name = $this->organization->name;
        $this->timezone = $this->organization->timezone ?: 'Asia/Kolkata';
        $this->currency = $this->organization->currency ?: 'INR';
        $this->theme = (string) $settings->get($this->organization, 'ui.theme', 'system');
        $this->refreshBrandingUrls($settings);
    }

    public function save(OrganizationSettingsService $settings, AuthorizationService $authorization): void
    {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3'],
            'theme' => ['required', 'in:light,dark,system'],
        ]);

        $theme = $validated['theme'];
        unset($validated['theme']);
        $this->organization->update($validated);
        $settings->set($this->organization, 'ui.theme', $theme);
        $this->successMessage = 'Organization settings saved successfully.';
    }

    public function render(AuthorizationService $authorization): View
    {
        $this->ensureCanManage($authorization);
        return view('core::livewire.organizations.self-settings')
            ->layout('core::layouts.organization-admin', [
                'pageTitle' => 'Organization settings',
                'layoutOrganization' => $this->organization,
            ]);
    }


    public function saveBranding(OrganizationSettingsService $settings, AuthorizationService $authorization): void
    {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);

        $validated = $this->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:1024'],
        ]);

        $saved = false;
        if (! $this->logo && ! $this->favicon) {
            $this->addError('logo', 'Choose a logo or favicon before saving.');
            return;
        }

        $this->resetValidation(['logo', 'favicon']);
        foreach (['logo', 'favicon'] as $field) {
            if (! $this->{$field}) {
                continue;
            }

            $key = 'branding.' . $field;
            $oldPath = $settings->get($this->organization, $key);
            $path = $this->{$field}->storePublicly(
                'organizations/' . $this->organization->id . '/branding',
                'public',
            );
            $settings->set($this->organization, $key, $path);

            if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path) {
                Storage::disk('public')->delete($oldPath);
            }

            $this->{$field} = null;
            $saved = true;
        }

        $this->refreshBrandingUrls($settings);
        if ($saved) {
            $this->successMessage = 'Organization branding saved successfully.';
        }
    }

    private function refreshBrandingUrls(OrganizationSettingsService $settings): void
    {
        $logoPath = $settings->get($this->organization, 'branding.logo');
        $faviconPath = $settings->get($this->organization, 'branding.favicon');
        $this->logoUrl = is_string($logoPath) && $logoPath !== ''
            ? Storage::disk('public')->url($logoPath)
            : null;
        $this->faviconUrl = is_string($faviconPath) && $faviconPath !== ''
            ? Storage::disk('public')->url($faviconPath)
            : null;
    }

    private function ensureCanManage(AuthorizationService $authorization): void
    {
        $user = auth()->user();
        abort_unless($user && $authorization->can(
            $user,
            'organization.settings.manage',
            new AuthorizationContext($this->organization->id),
        ), 403);
    }
}

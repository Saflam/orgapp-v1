<?php

namespace Modules\Core\Livewire\System;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Models\Organization;
use Modules\Core\Models\SystemNotificationChannel;
use Modules\Core\Services\NotificationService;
use Modules\Core\Services\MetaWhatsAppConnectionTester;

class SystemWhatsAppSettings extends Component
{
    public bool $isEnabled = false;
    public bool $hasAccessToken = false;
    public string $phoneNumberId = '';
    public string $wabaId = '';
    public string $accessToken = '';
    public string $apiVersion = 'v23.0';
    public string $otpTemplateName = 'saflivo_login_otp';
    public string $otpTemplateLanguage = 'en_US';
    public string $utilityTemplateName = 'saflivo_notification_update';
    public string $utilityTemplateLanguage = 'en_US';
    public string $marketingTemplateName = '';
    public string $marketingTemplateLanguage = 'en_US';
    public string $successMessage = '';
    public string $connectionTestMessage = '';
    public string $connectionTestStatus = '';
    public ?string $connectionTestPhoneNumber = null;
    public ?string $connectionTestVerifiedName = null;
    public array $permissions = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $channel = SystemNotificationChannel::query()->where('channel', 'whatsapp')->first();
        $credentials = $channel?->credentials ?? [];
        $this->isEnabled = (bool) ($channel?->is_enabled ?? false);
        $this->hasAccessToken = ! empty($credentials['access_token']);
        $this->phoneNumberId = (string) ($credentials['phone_number_id'] ?? '');
        $this->wabaId = (string) ($credentials['waba_id'] ?? '');
        $this->apiVersion = (string) ($credentials['api_version'] ?? 'v23.0');
        $this->otpTemplateName = (string) ($credentials['otp_template_name'] ?? 'saflivo_login_otp');
        $this->otpTemplateLanguage = (string) ($credentials['otp_template_language'] ?? 'en_US');
        $this->utilityTemplateName = (string) ($credentials['template_name'] ?? 'saflivo_notification_update');
        $this->utilityTemplateLanguage = (string) ($credentials['template_language'] ?? 'en_US');
        $this->marketingTemplateName = (string) ($credentials['marketing_template_name'] ?? '');
        $this->marketingTemplateLanguage = (string) ($credentials['marketing_template_language'] ?? 'en_US');
        $this->loadOrganizationPermissions();
    }

    public function saveDefaultChannel(): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $validated = Validator::make([
            'isEnabled' => $this->isEnabled,
            'phoneNumberId' => $this->phoneNumberId,
            'wabaId' => $this->wabaId,
            'accessToken' => $this->accessToken,
            'apiVersion' => $this->apiVersion,
            'otpTemplateName' => $this->otpTemplateName,
            'otpTemplateLanguage' => $this->otpTemplateLanguage,
            'utilityTemplateName' => $this->utilityTemplateName,
            'utilityTemplateLanguage' => $this->utilityTemplateLanguage,
            'marketingTemplateName' => $this->marketingTemplateName,
            'marketingTemplateLanguage' => $this->marketingTemplateLanguage,
        ], [
            'isEnabled' => ['required', 'boolean'],
            'phoneNumberId' => [$this->isEnabled ? 'required' : 'nullable', 'string', 'max:100'],
            'wabaId' => ['nullable', 'string', 'max:100'],
            'accessToken' => [$this->isEnabled && ! $this->hasAccessToken ? 'required' : 'nullable', 'string', 'max:5000'],
            'apiVersion' => ['required', 'regex:/^v\d+\.\d+$/'],
            'otpTemplateName' => [$this->isEnabled ? 'required' : 'nullable', 'string', 'max:255'],
            'otpTemplateLanguage' => ['required', 'string', 'max:20'],
            'utilityTemplateName' => ['nullable', 'string', 'max:255'],
            'utilityTemplateLanguage' => ['required', 'string', 'max:20'],
            'marketingTemplateName' => ['nullable', 'string', 'max:255'],
            'marketingTemplateLanguage' => ['required', 'string', 'max:20'],
        ])->validate();

        $existing = SystemNotificationChannel::query()->where('channel', 'whatsapp')->first();
        $credentials = $existing?->credentials ?? [];
        $credentials = array_merge($credentials, [
            'phone_number_id' => trim((string) $validated['phoneNumberId']),
            'waba_id' => trim((string) $validated['wabaId']),
            'api_version' => $validated['apiVersion'],
            'otp_template_name' => trim((string) $validated['otpTemplateName']),
            'otp_template_language' => $validated['otpTemplateLanguage'],
            'template_name' => trim((string) $validated['utilityTemplateName']),
            'template_language' => $validated['utilityTemplateLanguage'],
            'marketing_template_name' => trim((string) $validated['marketingTemplateName']),
            'marketing_template_language' => $validated['marketingTemplateLanguage'],
        ]);
        if (trim((string) $validated['accessToken']) !== '') $credentials['access_token'] = trim((string) $validated['accessToken']);
        if (! $this->isEnabled && trim((string) $this->accessToken) === '' && empty($credentials['access_token'])) unset($credentials['access_token']);
        SystemNotificationChannel::query()->updateOrCreate(
            ['channel' => 'whatsapp'], ['is_enabled' => $this->isEnabled, 'credentials' => $credentials],
        );
        $this->accessToken = '';
        $this->hasAccessToken = ! empty($credentials['access_token']);
        $this->successMessage = 'Saflivo default WhatsApp channel settings saved.';
    }

    public function testDefaultConnection(MetaWhatsAppConnectionTester $tester): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $existing = SystemNotificationChannel::query()->where('channel', 'whatsapp')->first();
        $storedCredentials = $existing?->credentials ?? [];
        $token = trim($this->accessToken) !== '' ? trim($this->accessToken) : (string) ($storedCredentials['access_token'] ?? '');
        $result = $tester->test([
            'phone_number_id' => trim($this->phoneNumberId),
            'access_token' => $token,
            'api_version' => $this->apiVersion,
        ]);
        $this->connectionTestStatus = $result['ok'] ? 'success' : 'error';
        $this->connectionTestMessage = $result['message'];
        $this->connectionTestPhoneNumber = $result['phone_number'];
        $this->connectionTestVerifiedName = $result['verified_name'];
    }

    public function saveOrganizationPermissions(int $organizationId, NotificationService $notifications): void
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        $organization = Organization::query()->findOrFail($organizationId);
        $row = $this->permissions[$organizationId] ?? [];
        $validated = Validator::make($row, [
            'is_allowed' => ['required', 'boolean'],
            'use_system_default' => ['required', 'boolean'],
            'utility_allowed' => ['required', 'boolean'],
            'marketing_allowed' => ['required', 'boolean'],
        ])->validate();
        $notifications->updateWhatsAppPermissions(
            $organization, 'whatsapp', (bool) $validated['is_allowed'],
            (bool) $validated['use_system_default'], (bool) $validated['utility_allowed'],
            (bool) $validated['marketing_allowed'],
        );
        $this->loadOrganizationPermissions();
        $this->successMessage = 'WhatsApp permissions updated for ' . $organization->name . '.';
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->is_system_admin, 403);
        return view('core::livewire.system.whatsapp-settings')
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'WhatsApp settings']);
    }

    private function loadOrganizationPermissions(): void
    {
        $this->permissions = [];
        foreach (Organization::query()->orderBy('name')->get() as $organization) {
            $channel = $organization->notificationChannels()->where('channel', 'whatsapp')->first();
            $this->permissions[$organization->id] = [
                'is_allowed' => (bool) ($channel?->is_allowed ?? false),
                'use_system_default' => (bool) ($channel?->use_system_default ?? false),
                'utility_allowed' => (bool) ($channel?->utility_allowed ?? false),
                'marketing_allowed' => (bool) ($channel?->marketing_allowed ?? false),
            ];
        }
    }
}

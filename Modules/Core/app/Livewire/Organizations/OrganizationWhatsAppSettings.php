<?php

namespace Modules\Core\Livewire\Organizations;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationNotificationChannel;
use Modules\Core\Models\SystemNotificationChannel;
use Modules\Core\Models\OrganizationWhatsAppUsageLimit;
use Modules\Core\Models\WhatsAppMessageUsage;
use Modules\Core\Models\WhatsAppUsageLimitRequest;
use Modules\Core\Services\WhatsAppUsageLimitService;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\NotificationChannelResolver;
use Modules\Core\Services\NotificationService;
use Modules\Core\Services\MetaWhatsAppConnectionTester;

class OrganizationWhatsAppSettings extends Component
{
    public Organization $organization;
    public bool $isAllowed = false;
    public bool $isEnabled = false;
    public bool $useSystemDefault = false;
    public bool $utilityAllowed = false;
    public bool $marketingAllowed = false;
    public bool $hasAccessToken = false;
    public string $phoneNumberId = '';
    public string $wabaId = '';
    public string $accessToken = '';
    public string $apiVersion = 'v23.0';
    public string $otpTemplateName = 'organization_login_otp';
    public string $otpTemplateLanguage = 'en_US';
    public string $utilityTemplateName = '';
    public string $utilityTemplateLanguage = 'en_US';
    public string $marketingTemplateName = '';
    public string $marketingTemplateLanguage = 'en_US';
    public ?string $effectiveSource = null;
    public string $successMessage = '';
    public string $connectionTestMessage = '';
    public string $connectionTestStatus = '';
    public ?string $connectionTestPhoneNumber = null;
    public ?string $connectionTestVerifiedName = null;
    public int $requestedMonthlyLimit = 0;
    public string $limitRequestReason = '';
    public string $limitRequestMessage = '';

    public function mount(CurrentOrganization $currentOrganization, AuthorizationService $authorization, NotificationChannelResolver $resolver): void
    {
        $this->organization = $currentOrganization->get();
        $this->ensureCanManage($authorization);
        $channel = $this->organization->notificationChannels()->where('channel', 'whatsapp')->first();
        $credentials = $channel?->credentials ?? [];
        $this->isAllowed = (bool) ($channel?->is_allowed ?? false);
        $this->isEnabled = (bool) ($channel?->is_enabled ?? false);
        $this->useSystemDefault = (bool) ($channel?->use_system_default ?? false);
        $this->utilityAllowed = (bool) ($channel?->utility_allowed ?? false);
        $this->marketingAllowed = (bool) ($channel?->marketing_allowed ?? false);
        $this->hasAccessToken = ! empty($credentials['access_token']);
        $this->phoneNumberId = (string) ($credentials['phone_number_id'] ?? '');
        $this->wabaId = (string) ($credentials['waba_id'] ?? '');
        $this->apiVersion = (string) ($credentials['api_version'] ?? 'v23.0');
        $this->otpTemplateName = (string) ($credentials['otp_template_name'] ?? 'organization_login_otp');
        $this->otpTemplateLanguage = (string) ($credentials['otp_template_language'] ?? 'en_US');
        $this->utilityTemplateName = (string) ($credentials['template_name'] ?? '');
        $this->utilityTemplateLanguage = (string) ($credentials['template_language'] ?? 'en_US');
        $this->marketingTemplateName = (string) ($credentials['marketing_template_name'] ?? '');
        $this->marketingTemplateLanguage = (string) ($credentials['marketing_template_language'] ?? 'en_US');
        $this->effectiveSource = $resolver->resolve($this->organization, 'whatsapp', 'otp')['source'] ?? null;
    }

    public function save(NotificationService $notifications, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        abort_unless($this->isAllowed, 403, 'The System Admin has not enabled WhatsApp for this organization.');
        $existing = $this->organization->notificationChannels()->where('channel', 'whatsapp')->first();
        $existingCredentials = $existing?->credentials ?? [];
        $validated = Validator::make([
            'isEnabled' => $this->isEnabled, 'phoneNumberId' => $this->phoneNumberId,
            'wabaId' => $this->wabaId, 'accessToken' => $this->accessToken,
            'apiVersion' => $this->apiVersion, 'otpTemplateName' => $this->otpTemplateName,
            'otpTemplateLanguage' => $this->otpTemplateLanguage, 'utilityTemplateName' => $this->utilityTemplateName,
            'utilityTemplateLanguage' => $this->utilityTemplateLanguage, 'marketingTemplateName' => $this->marketingTemplateName,
            'marketingTemplateLanguage' => $this->marketingTemplateLanguage,
        ], [
            'isEnabled' => ['required', 'boolean'],
            'phoneNumberId' => [$this->isEnabled ? 'required' : 'nullable', 'string', 'max:100'],
            'wabaId' => ['nullable', 'string', 'max:100'],
            'accessToken' => [$this->isEnabled && ! $this->hasAccessToken ? 'required' : 'nullable', 'string', 'max:5000'],
            'apiVersion' => ['required', 'regex:/^v\d+\.\d+$/'],
            'otpTemplateName' => [$this->isEnabled ? 'required' : 'nullable', 'string', 'max:255'], 'otpTemplateLanguage' => ['required', 'string', 'max:20'],
            'utilityTemplateName' => ['nullable', 'string', 'max:255'], 'utilityTemplateLanguage' => ['required', 'string', 'max:20'],
            'marketingTemplateName' => ['nullable', 'string', 'max:255'], 'marketingTemplateLanguage' => ['required', 'string', 'max:20'],
        ])->validate();
        $credentials = array_merge($existingCredentials, [
            'phone_number_id' => trim((string) $validated['phoneNumberId']), 'waba_id' => trim((string) $validated['wabaId']),
            'api_version' => $validated['apiVersion'], 'otp_template_name' => trim((string) $validated['otpTemplateName']),
            'otp_template_language' => $validated['otpTemplateLanguage'], 'template_name' => trim((string) $validated['utilityTemplateName']),
            'template_language' => $validated['utilityTemplateLanguage'], 'marketing_template_name' => trim((string) $validated['marketingTemplateName']),
            'marketing_template_language' => $validated['marketingTemplateLanguage'],
        ]);
        if (trim((string) $validated['accessToken']) !== '') $credentials['access_token'] = trim((string) $validated['accessToken']);
        $notifications->configureOrganizationChannel($this->organization, 'whatsapp', (bool) $validated['isEnabled'], $credentials);
        $this->accessToken = '';
        $this->hasAccessToken = ! empty($credentials['access_token']);
        $this->successMessage = 'Organization WhatsApp channel settings saved.';
    }


    public function testOrganizationConnection(MetaWhatsAppConnectionTester $tester, AuthorizationService $authorization): void
    {
        $this->ensureCanManage($authorization);
        abort_unless($this->isAllowed, 403, 'The System Admin has not enabled WhatsApp for this organization.');
        $existing = $this->organization->notificationChannels()->where('channel', 'whatsapp')->first();
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

    public function requestLimitIncrease(WhatsAppUsageLimitService $limits): void
    {
        $this->ensureCanManage(app(AuthorizationService::class));
        $usage = $limits->currentUsage($this->organization);
        abort_if($usage['limit'] === null, 422, 'This organization has no monthly limit. Ask the System Admin if a limit should be configured.');
        abort_unless($usage['exhausted'], 422, 'An increase request is available after the current monthly limit is reached.');
        $validated = Validator::make([
            'requestedMonthlyLimit' => $this->requestedMonthlyLimit,
            'limitRequestReason' => $this->limitRequestReason,
        ], [
            'requestedMonthlyLimit' => ['required', 'integer', 'min:' . ((int) $usage['limit'] + 1), 'max:10000000'],
            'limitRequestReason' => ['required', 'string', 'min:10', 'max:2000'],
        ])->validate();
        abort_if(WhatsAppUsageLimitRequest::query()->where('organization_id', $this->organization->id)->where('status', 'pending')->exists(), 422, 'There is already a pending WhatsApp usage-limit request.');
        WhatsAppUsageLimitRequest::query()->create([
            'organization_id' => $this->organization->id,
            'requested_by' => auth()->id(),
            'current_limit' => $usage['limit'],
            'requested_limit' => $validated['requestedMonthlyLimit'],
            'reason' => $validated['limitRequestReason'],
            'status' => 'pending',
        ]);
        $this->limitRequestReason = '';
        $this->limitRequestMessage = 'Usage-limit increase request submitted to the System Admin.';
    }

    public function render(NotificationChannelResolver $resolver, WhatsAppUsageLimitService $limits): View
    {
        $this->ensureCanManage(app(AuthorizationService::class));
        $this->effectiveSource = $resolver->resolve($this->organization, 'whatsapp', 'otp')['source'] ?? null;
        $usage = $limits->currentUsage($this->organization);
        $limitRequests = WhatsAppUsageLimitRequest::query()->where('organization_id', $this->organization->id)->latest()->limit(10)->get();
        return view('core::livewire.organizations.whatsapp-settings', compact('usage', 'limitRequests'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Notification settings', 'layoutOrganization' => $this->organization]);
    }

    private function ensureCanManage(AuthorizationService $authorization): void
    {
        abort_unless(auth()->user() && $authorization->can(auth()->user(), 'notification.channel.manage', new AuthorizationContext($this->organization->id)), 403);
    }
}

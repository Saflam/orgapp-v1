<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;

class OrganizationService
{
    public function __construct(
        private readonly OrganizationModuleService $moduleService,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly SuperAdminProvisioningService $superAdminProvisioningService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function create(array $data): Organization
    {
        return DB::transaction(function () use ($data) {
            $this->ensureSuperAdminEmailIsAvailable(
                $data['super_admin']['email']
            );

            $organization = Organization::create(
                $data['organization']
            );

            $this->initializeModules($organization);
            $this->initializeNotificationChannels(
                $organization,
                $data['notification_channels'] ?? [],
            );

            $this->superAdminProvisioningService->provision(
                $organization,
                $data['super_admin'],
            );

            return $organization->fresh();
        });
    }

    public function activate(Organization $organization): Organization
    {
        $organization->update([
            'is_active' => true,
        ]);

        return $organization->fresh();
    }

    public function deactivate(Organization $organization): Organization
    {
        $organization->update([
            'is_active' => false,
        ]);

        return $organization->fresh();
    }

    private function initializeNotificationChannels(Organization $organization, array $channels): void
    {
        foreach (['whatsapp', 'sms'] as $channel) {
            $allowed = (bool) ($channels[$channel]['use_system_default'] ?? false);

            $this->notificationService->setSystemDefaultAccess(
                $organization,
                $channel,
                $allowed,
            );
        }
    }

    private function ensureSuperAdminEmailIsAvailable(string $email): void
    {
        if (User::query()->where('email', $email)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'super_admin.email' =>
                    'A user with this email address already exists.',
            ]);
        }
    }

    private function initializeModules(Organization $organization): void
    {
        // Required modules are initialized explicitly rather than relying on
        // their presence in the optional-module registry iteration. If a
        // required module is unavailable in this deployment, enable() throws
        // and the surrounding organization-creation transaction rolls back.
        foreach ($this->moduleService->requiredModules() as $module) {
            $this->moduleService->enable(
                $organization,
                $module,
            );
        }

        // Optional modules start disabled for every new organization. Their
        // activation remains an organization-specific decision.
        foreach ($this->moduleRegistry->enabledNames() as $module) {
            if ($this->moduleService->isRequired($module)) {
                continue;
            }

            $this->moduleService->disable(
                $organization,
                $module,
            );
        }
    }
}
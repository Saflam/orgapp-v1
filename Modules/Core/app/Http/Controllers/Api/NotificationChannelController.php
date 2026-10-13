<?php

namespace Modules\Core\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\NotificationChannelResolver;
use Modules\Core\Services\NotificationService;
use Modules\Core\Models\SystemNotificationChannel;

class NotificationChannelController
{
    public function systemShow(string $channel): JsonResponse
    {
        $configured = SystemNotificationChannel::query()
            ->where('channel', $channel)
            ->first();

        return response()->json([
            'channel' => $channel,
            'is_enabled' => $configured?->is_enabled ?? false,
            'has_credentials' => ! empty($configured?->credentials),
        ]);
    }

    public function systemUpdate(
        string $channel,
        Request $request,
        NotificationService $notificationService,
    ): JsonResponse {
        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'credentials' => ['sometimes', 'array'],
        ]);

        $configured = $notificationService->configureSystemChannel(
            $channel,
            $validated['is_enabled'],
            $validated['credentials'] ?? [],
        );

        return response()->json([
            'channel' => $configured->channel,
            'is_enabled' => $configured->is_enabled,
            'has_credentials' => ! empty($configured->credentials),
        ]);
    }

    public function organizationShow(
        string $channel,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorizationService,
        NotificationChannelResolver $resolver,
    ): JsonResponse {
        $organization = $currentOrganization->get();
        $this->ensureOrganizationPermission($authorizationService, $organization);

        $configuration = $resolver->resolve($organization, $channel);

        $configured = $organization->notificationChannels()
            ->where('channel', $channel)
            ->first();

        return response()->json([
            'channel' => $channel,
            'is_enabled' => $configured?->is_enabled ?? false,
            'use_system_default' => $configured?->use_system_default ?? false,
            'has_credentials' => ! empty($configured?->credentials),
            'effective_source' => $configuration['source'] ?? null,
        ]);
    }

    public function organizationUpdate(
        string $channel,
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorizationService,
        NotificationService $notificationService,
    ): JsonResponse {
        $organization = $currentOrganization->get();
        $this->ensureOrganizationPermission($authorizationService, $organization);

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'credentials' => ['sometimes', 'array'],
        ]);

        $configured = $notificationService->configureOrganizationChannel(
            $organization,
            $channel,
            $validated['is_enabled'],
            $validated['credentials'] ?? [],
        );

        return response()->json([
            'channel' => $configured->channel,
            'is_enabled' => $configured->is_enabled,
            'use_system_default' => $configured->use_system_default,
            'has_credentials' => ! empty($configured->credentials),
        ]);
    }

    public function organizationDefault(
        string $channel,
        Request $request,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorizationService,
        NotificationService $notificationService,
    ): JsonResponse {
        $organization = $currentOrganization->get();
        $this->ensureOrganizationPermission($authorizationService, $organization);

        $validated = $request->validate([
            'use_system_default' => ['required', 'boolean'],
        ]);

        $configured = $notificationService->setSystemDefaultAccess(
            $organization,
            $channel,
            $validated['use_system_default'],
        );

        return response()->json([
            'channel' => $configured->channel,
            'is_enabled' => $configured->is_enabled,
            'use_system_default' => $configured->use_system_default,
            'has_credentials' => ! empty($configured->credentials),
        ]);
    }

    private function ensureOrganizationPermission(
        AuthorizationService $authorizationService,
        Organization $organization,
    ): void {
        $user = request()->user();

        if ($user === null || ! $authorizationService->can(
            $user,
            'notification.channel.manage',
            new AuthorizationContext($organization->id),
        )) {
            abort(403, 'You do not have permission to manage notification channels.');
        }
    }
}

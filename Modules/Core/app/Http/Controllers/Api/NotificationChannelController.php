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
        abort_unless(in_array($channel, ['email', 'sms', 'whatsapp', 'push'], true), 404);
        $existing = SystemNotificationChannel::query()->where('channel', $channel)->first();
        $rules = [
            'is_enabled' => ['required', 'boolean'],
            'credentials' => ['sometimes', 'array'],
        ];
        if ($channel === 'whatsapp') {
            $rules['credentials.template_language'] = ['sometimes', 'string', 'max:20'];
            $rules['credentials.waba_id'] = ['sometimes', 'nullable', 'string', 'max:100'];
            $rules['credentials.base_url'] = ['sometimes', 'url', 'starts_with:https://'];
            $rules['credentials.otp_template_language'] = ['sometimes', 'string', 'max:20'];
            $rules['credentials.marketing_template_language'] = ['sometimes', 'string', 'max:20'];
            $rules['credentials.api_version'] = ['sometimes', 'regex:/^v\d+\.\d+$/'];
        }
        if ($channel === 'whatsapp' && $request->boolean('is_enabled')) {
            $incomingMetaCredentials = $request->has('credentials.phone_number_id')
                || ! empty($existing?->credentials['phone_number_id']);
            if ($incomingMetaCredentials && ($request->has('credentials') || empty($existing?->credentials))) {
                $rules['credentials'] = ['required', 'array'];
                $rules['credentials.phone_number_id'] = ['required', 'string', 'max:100'];
                $rules['credentials.access_token'] = ['required', 'string', 'max:5000'];
                $rules['credentials.otp_template_name'] = ['required', 'string', 'max:255'];
                $rules['credentials.otp_template_language'] = ['sometimes', 'string', 'max:20'];
            }
        }
        if ($channel === 'whatsapp') {
            $rules['credentials.template_name'] = ['sometimes', 'string', 'max:255'];
            $rules['credentials.marketing_template_name'] = ['sometimes', 'string', 'max:255'];
            $rules['credentials.marketing_template_language'] = ['sometimes', 'string', 'max:20'];
        }
        $validated = $request->validate($rules);

        $credentials = array_merge($existing?->credentials ?? [], $validated['credentials'] ?? []);
        if (isset($validated['credentials']['access_token']) && trim((string) $validated['credentials']['access_token']) === '') {
            $credentials['access_token'] = $existing?->credentials['access_token'] ?? '';
        }
        $configured = $notificationService->configureSystemChannel(
            $channel,
            $validated['is_enabled'],
            $credentials,
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
            'is_allowed' => $configured?->is_allowed ?? false,
            'utility_allowed' => $configured?->utility_allowed ?? false,
            'marketing_allowed' => $configured?->marketing_allowed ?? false,
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

        abort_unless(in_array($channel, ['email', 'sms', 'whatsapp', 'push'], true), 404);
        $existing = $organization->notificationChannels()->where('channel', $channel)->first();
        if (in_array($channel, ['whatsapp', 'sms'], true) && $existing?->is_allowed !== true) {
            abort(403, 'The System Admin has not allowed this organization to use this notification channel.');
        }

        $rules = [
            'is_enabled' => ['required', 'boolean'],
            'credentials' => ['sometimes', 'array'],
        ];
        if ($channel === 'whatsapp') {
            $rules['credentials.template_language'] = ['sometimes', 'string', 'max:20'];
            $rules['credentials.waba_id'] = ['sometimes', 'nullable', 'string', 'max:100'];
            $rules['credentials.base_url'] = ['sometimes', 'url', 'starts_with:https://'];
            $rules['credentials.otp_template_language'] = ['sometimes', 'string', 'max:20'];
            $rules['credentials.marketing_template_language'] = ['sometimes', 'string', 'max:20'];
            $rules['credentials.api_version'] = ['sometimes', 'regex:/^v\d+\.\d+$/'];
        }
        if ($channel === 'whatsapp' && $request->boolean('is_enabled')) {
            $incomingMetaCredentials = $request->has('credentials.phone_number_id')
                || ! empty($existing?->credentials['phone_number_id']);
            if ($incomingMetaCredentials && ($request->has('credentials') || empty($existing?->credentials))) {
                $rules['credentials'] = ['required', 'array'];
                $rules['credentials.phone_number_id'] = ['required', 'string', 'max:100'];
                $rules['credentials.access_token'] = ['required', 'string', 'max:5000'];
                $rules['credentials.otp_template_name'] = ['required', 'string', 'max:255'];
                $rules['credentials.otp_template_language'] = ['sometimes', 'string', 'max:20'];
            }
        }
        if ($channel === 'whatsapp') {
            $rules['credentials.template_name'] = ['sometimes', 'string', 'max:255'];
            $rules['credentials.marketing_template_name'] = ['sometimes', 'string', 'max:255'];
            $rules['credentials.marketing_template_language'] = ['sometimes', 'string', 'max:20'];
            $rules['credentials.otp_template_language'] = ['sometimes', 'string', 'max:20'];
        }
        $validated = $request->validate($rules);

        $credentials = array_merge($existing?->credentials ?? [], $validated['credentials'] ?? []);
        if (isset($validated['credentials']['access_token']) && trim((string) $validated['credentials']['access_token']) === '') {
            $credentials['access_token'] = $existing?->credentials['access_token'] ?? '';
        }
        $configured = $notificationService->configureOrganizationChannel(
            $organization,
            $channel,
            $validated['is_enabled'],
            $credentials,
        );

        return response()->json([
            'channel' => $configured->channel,
            'is_allowed' => $configured->is_allowed,
            'is_enabled' => $configured->is_enabled,
            'use_system_default' => $configured->use_system_default,
            'has_credentials' => ! empty($configured->credentials),
        ]);
    }

    public function organizationPermissions(
        Organization $organization,
        string $channel,
        Request $request,
        NotificationService $notificationService,
    ): JsonResponse {
        abort_unless(request()->user()?->is_system_admin, 403, 'Only the system administrator can manage notification permissions.');
        abort_unless(in_array($channel, ['whatsapp', 'sms'], true), 404);

        $validated = $request->validate([
            'is_allowed' => ['required', 'boolean'],
            'use_system_default' => ['required', 'boolean'],
            'utility_allowed' => ['sometimes', 'boolean'],
            'marketing_allowed' => ['sometimes', 'boolean'],
        ]);

        $current = $organization->notificationChannels()->where('channel', $channel)->first();
        $configured = $notificationService->updateWhatsAppPermissions(
            $organization, $channel,
            $validated['is_allowed'],
            $validated['use_system_default'],
            (bool) ($validated['utility_allowed'] ?? $current?->utility_allowed ?? false),
            (bool) ($validated['marketing_allowed'] ?? $current?->marketing_allowed ?? false),
        );

        return response()->json([
            'channel' => $configured->channel,
            'is_allowed' => $configured->is_allowed,
            'use_system_default' => $configured->use_system_default,
            'utility_allowed' => $configured->utility_allowed,
            'marketing_allowed' => $configured->marketing_allowed,
            'is_enabled' => $configured->is_enabled,
            'has_credentials' => ! empty($configured->credentials),
        ]);
    }

    public function organizationDefault(
        Organization $organization,
        string $channel,
        Request $request,
        NotificationService $notificationService,
    ): JsonResponse {
        abort_unless(request()->user()?->is_system_admin, 403, 'Only the system administrator can manage system-default notification access.');
        abort_unless(in_array($channel, ['sms', 'whatsapp'], true), 404);

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
            'is_allowed' => $configured->is_allowed,
            'is_enabled' => $configured->is_enabled,
            'use_system_default' => $configured->use_system_default,
            'has_credentials' => ! empty($configured->credentials),
        ]);
    }

    public function systemWhatsAppUsage(Request $request): JsonResponse
    {
        abort_unless(request()->user()?->is_system_admin, 403);
        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);
        $query = \Illuminate\Support\Facades\DB::table('whatsapp_message_usages')
            ->leftJoin('organizations', 'organizations.id', '=', 'whatsapp_message_usages.organization_id')
            ->selectRaw('whatsapp_message_usages.organization_id, organizations.name as organization_name, whatsapp_message_usages.service_category, whatsapp_message_usages.channel_source, whatsapp_message_usages.status, COUNT(*) as message_count')
            ->groupBy('whatsapp_message_usages.organization_id', 'organizations.name', 'whatsapp_message_usages.service_category', 'whatsapp_message_usages.channel_source', 'whatsapp_message_usages.status');
        if (isset($validated['from'])) $query->where('whatsapp_message_usages.created_at', '>=', $validated['from']);
        if (isset($validated['to'])) $query->where('whatsapp_message_usages.created_at', '<=', $validated['to']);
        return response()->json(['data' => $query->orderBy('organizations.name')->get()]);
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

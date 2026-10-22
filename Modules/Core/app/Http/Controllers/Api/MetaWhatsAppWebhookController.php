<?php

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\NotificationDelivery;
use Modules\Core\Models\WhatsAppMessageUsage;
use Symfony\Component\HttpFoundation\Response;

class MetaWhatsAppWebhookController
{
    public function verify(Request $request): Response
    {
        $verifyToken = (string) config('core.whatsapp.webhook_verify_token', '');
        $mode = (string) $request->query('hub.mode', $request->query('hub_mode', ''));
        $token = (string) $request->query('hub.verify_token', $request->query('hub_verify_token', ''));
        $challenge = $request->query('hub.challenge', $request->query('hub_challenge')); 

        if ($verifyToken === '' || $mode !== 'subscribe' || ! is_string($challenge) || ! hash_equals($verifyToken, $token)) {
            return response('Forbidden', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request): JsonResponse
    {
        $appSecret = (string) config('core.whatsapp.app_secret', '');
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $rawBody = $request->getContent();

        if ($appSecret === '' || ! preg_match('/^sha256=[a-f0-9]{64}$/i', $signature)) {
            return response()->json(['message' => 'Webhook signature is invalid.'], 401);
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Webhook signature is invalid.'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload) || ($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response()->json(['message' => 'Unsupported webhook payload.'], 400);
        }

        $processed = 0;
        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                foreach (($value['statuses'] ?? []) as $status) {
                    if (! is_array($status) || ! is_string($status['id'] ?? null) || ! is_string($status['status'] ?? null)) {
                        continue;
                    }
                    if ($this->processStatus($status)) {
                        $processed++;
                    }
                }
            }
        }

        return response()->json(['received' => true, 'processed_statuses' => $processed]);
    }

    private function processStatus(array $status): bool
    {
        $messageId = $status['id'];
        $state = strtolower($status['status']);
        $timestamp = (string) ($status['timestamp'] ?? '');
        $error = $status['errors'][0] ?? [];
        $pricing = $status['pricing'] ?? [];
        $errorCode = is_array($error) ? (string) ($error['code'] ?? '') : '';
        $eventHash = hash('sha256', implode('|', [$messageId, $state, $timestamp, $errorCode]));

        if (! in_array($state, ['sent', 'delivered', 'read', 'failed'], true)) {
            return false;
        }

        return DB::transaction(function () use ($messageId, $state, $timestamp, $error, $pricing, $errorCode, $eventHash): bool {
            $inserted = DB::table('whatsapp_webhook_events')->insertOrIgnore([
                'event_hash' => $eventHash,
                'provider_message_id' => $messageId,
                'status' => $state,
                'provider_timestamp' => ctype_digit($timestamp) ? date('Y-m-d H:i:s', (int) $timestamp) : null,
                'error_code' => $errorCode !== '' ? $errorCode : null,
                'error_title' => is_array($error) ? ($error['title'] ?? null) : null,
                'pricing_category' => is_array($pricing) ? ($pricing['category'] ?? null) : null,
                'billable' => is_array($pricing) && array_key_exists('billable', $pricing) ? (bool) $pricing['billable'] : null,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return false;
            }

            $usage = WhatsAppMessageUsage::query()->where('provider_message_id', $messageId)->lockForUpdate()->first();
            if (! $usage) {
                // Keep the event so retries are idempotent, but do not expose unknown IDs or recipient data.
                return true;
            }

            // Keep `status` as API acceptance/failure for quota accounting. Webhook
            // delivery state is tracked separately so delivered messages still count
            // toward monthly usage after the current period rolls over.
            $current = strtolower((string) ($usage->delivery_status ?: 'accepted'));
            $next = $this->nextStatus($current, $state);
            $metadata = $usage->metadata ?? [];
            $metadata['last_webhook_status'] = $state;
            $metadata['last_webhook_at'] = ctype_digit($timestamp) ? date(DATE_ATOM, (int) $timestamp) : now()->toIso8601String();
            if ($errorCode !== '') {
                $metadata['delivery_error'] = [
                    'code' => $errorCode,
                    'title' => is_array($error) ? ($error['title'] ?? null) : null,
                    'message' => is_array($error) ? ($error['message'] ?? null) : null,
                ];
            }

            $updates = ['metadata' => $metadata];
            if ($next !== $current) {
                $updates['delivery_status'] = $next;
            }
            if ($state === 'delivered' && ! $usage->delivered_at) {
                $updates['delivered_at'] = ctype_digit($timestamp) ? date('Y-m-d H:i:s', (int) $timestamp) : now();
            }
            if (is_array($pricing)) {
                if (isset($pricing['category'])) $updates['meta_pricing_category'] = $pricing['category'];
                if (array_key_exists('billable', $pricing)) $updates['meta_billable'] = (bool) $pricing['billable'];
            }
            $usage->forceFill($updates)->save();

            if ($usage->notification_delivery_id) {
                $delivery = NotificationDelivery::query()->whereKey($usage->notification_delivery_id)->lockForUpdate()->first();
                if ($delivery) {
                    $deliveryStatus = $this->nextStatus(strtolower((string) $delivery->status), $state);
                    $deliveryUpdates = ['status' => $deliveryStatus];
                    if ($state === 'failed') {
                        $title = is_array($error) ? ($error['title'] ?? null) : null;
                        $deliveryUpdates['error'] = trim(($errorCode !== '' ? "Meta {$errorCode}: " : 'Meta delivery failed: ') . (is_string($title) ? $title : 'No failure description provided.'));
                    } elseif ($state === 'delivered') {
                        $deliveryUpdates['error'] = null;
                    }
                    $delivery->forceFill($deliveryUpdates)->save();
                }
            }

            return true;
        });
    }

    private function nextStatus(string $current, string $incoming): string
    {
        $rank = ['accepted' => 0, 'queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];
        if ($incoming === 'failed') {
            return in_array($current, ['delivered', 'read'], true) ? $current : 'failed';
        }
        if (! array_key_exists($incoming, $rank)) {
            return $current;
        }
        if ($current === 'failed' || in_array($current, ['delivered', 'read'], true) && ($rank[$incoming] ?? 0) < ($rank[$current] ?? 0)) {
            return $current;
        }
        return ($rank[$incoming] ?? 0) >= ($rank[$current] ?? 0) ? $incoming : $current;
    }
}

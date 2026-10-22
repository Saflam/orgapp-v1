<?php

namespace Modules\Core\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\WhatsAppCostReconciliationBatch;
use Modules\Core\Models\WhatsAppCostReconciliationItem;
use Modules\Core\Models\WhatsAppMessageUsage;
use RuntimeException;
use Throwable;

class WhatsAppCostReconciliationService
{
    private const REQUIRED_HEADERS = [
        'provider_message_id',
        'cost_amount',
        'currency',
        'pricing_category',
        'billable',
        'occurred_at',
    ];

    /**
     * Import the documented normalized CSV format. This intentionally does not
     * assume that every Meta export has the same columns.
     */
    public function importCsv(UploadedFile $file, ?int $userId): WhatsAppCostReconciliationBatch
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded CSV could not be opened.');
        }

        try {
            $headers = fgetcsv($handle);
            if (! is_array($headers)) {
                throw new RuntimeException('The CSV is empty.');
            }

            $headers = array_map(
                fn ($header) => strtolower(trim(ltrim((string) $header, "\xEF\xBB\xBF"))),
                $headers
            );
            $positions = [];
            foreach (self::REQUIRED_HEADERS as $required) {
                $position = array_search($required, $headers, true);
                if ($position === false) {
                    throw new RuntimeException('Missing required CSV column: ' . $required);
                }
                $positions[$required] = $position;
            }

            return DB::transaction(function () use ($handle, $file, $userId, $positions): WhatsAppCostReconciliationBatch {
                $batch = WhatsAppCostReconciliationBatch::query()->create([
                    'source_filename' => basename($file->getClientOriginalName()),
                    'imported_by' => $userId,
                ]);

                $seenInFile = [];
                $counts = [
                    'row_count' => 0,
                    'matched_count' => 0,
                    'unmatched_count' => 0,
                    'duplicate_count' => 0,
                    'invalid_count' => 0,
                    'discrepancy_count' => 0,
                ];

                while (($row = fgetcsv($handle)) !== false) {
                    if ($row === [null] || $row === []) {
                        continue;
                    }
                    $counts['row_count']++;
                    if ($counts['row_count'] > 10000) {
                        throw new RuntimeException('A single import may contain at most 10,000 data rows.');
                    }

                    $get = static fn (string $key): string => trim((string) ($row[$positions[$key]] ?? ''));
                    $messageId = $get('provider_message_id');
                    $amountText = $get('cost_amount');
                    $currency = strtoupper($get('currency'));
                    $category = $get('pricing_category');
                    $billableText = strtolower($get('billable'));
                    $occurredText = $get('occurred_at');

                    $normalizedAmount = $this->normalizeProviderAmount($amountText);
                    $amountValid = $normalizedAmount !== null;
                    $currencyValid = (bool) preg_match('/^[A-Z]{3}$/', $currency);
                    $billableValid = in_array($billableText, ['1', '0', 'true', 'false', 'yes', 'no'], true);
                    $occurredAt = null;
                    if ($occurredText !== '') {
                        try {
                            $occurredAt = Carbon::parse($occurredText);
                        } catch (Throwable) {
                            $occurredAt = null;
                        }
                    }

                    $issue = null;
                    $status = 'invalid';
                    $usage = null;
                    if ($messageId === '' || strlen($messageId) > 255) {
                        $issue = 'Missing or invalid provider message ID.';
                    } elseif (! $amountValid) {
                        $issue = 'Cost amount must be a non-negative number.';
                    } elseif (! $currencyValid) {
                        $issue = 'Currency must be a three-letter ISO currency code.';
                    } elseif (! $billableValid) {
                        $issue = 'Billable must be true/false, yes/no, or 1/0.';
                    } elseif ($occurredText !== '' && ! $occurredAt) {
                        $issue = 'Occurred-at value could not be parsed.';
                    } elseif (isset($seenInFile[$messageId])) {
                        $status = 'duplicate';
                        $issue = 'Duplicate provider message ID in this CSV.';
                    } else {
                        $seenInFile[$messageId] = true;
                        // Lock the usage row before checking prior successful imports. This serializes
                        // concurrent imports for an existing provider message ID.
                        $usage = WhatsAppMessageUsage::query()
                            ->where('provider_message_id', $messageId)
                            ->lockForUpdate()
                            ->first();
                        $priorImport = WhatsAppCostReconciliationItem::query()
                            ->where('provider_message_id', $messageId)
                            ->whereIn('reconciliation_status', ['matched', 'discrepancy'])
                            ->exists();

                        if ($priorImport) {
                            $status = 'duplicate';
                            $issue = 'Provider message ID was already reconciled in an earlier import.';
                            $usage = null;
                        } elseif ($usage) {
                            $status = 'matched';
                        } else {
                            $status = 'unmatched';
                            $issue = 'No internal WhatsApp usage record matches this provider message ID.';
                        }
                    }

                    $billable = $billableValid
                        ? in_array($billableText, ['1', 'true', 'yes'], true)
                        : null;
                    $comparison = $status === 'matched' && $usage
                        ? $this->compareUsageWithProvider($usage, $normalizedAmount, $currencyValid ? $currency : null, $category !== '' ? substr($category, 0, 40) : null, $billable)
                        : ['details' => [], 'summary' => null];
                    if ($status === 'matched' && $comparison['details'] !== []) {
                        $status = 'discrepancy';
                        $issue = $comparison['summary'];
                    }

                    $item = WhatsAppCostReconciliationItem::query()->create([
                        'batch_id' => $batch->id,
                        'usage_id' => $usage?->id,
                        'organization_id' => $usage?->organization_id,
                        'provider_message_id' => $messageId !== '' ? $messageId : null,
                        'provider_cost_amount' => $normalizedAmount,
                        'provider_cost_currency' => $currencyValid ? $currency : null,
                        'pricing_category' => $category !== '' ? substr($category, 0, 40) : null,
                        'billable' => $billable,
                        'provider_occurred_at' => $occurredAt,
                        'reconciliation_status' => $status,
                        'issue' => $issue,
                        'source_row' => ['values' => $row],
                        'internal_cost_amount' => $usage?->meta_cost_amount,
                        'internal_cost_currency' => $usage?->meta_cost_currency,
                        'internal_pricing_category' => $usage?->meta_pricing_category,
                        'internal_billable' => $usage?->meta_billable,
                        'discrepancy_details' => $comparison['details'] ?: null,
                    ]);

                    if (in_array($status, ['matched', 'discrepancy'], true) && $usage) {
                        $usage->forceFill([
                            'meta_cost_amount' => $item->provider_cost_amount,
                            'meta_cost_currency' => $item->provider_cost_currency,
                            'meta_pricing_category' => $item->pricing_category ?: $usage->meta_pricing_category,
                            'meta_billable' => $item->billable,
                        ])->save();
                    }

                    $counts[$status . '_count']++;
                }

                $batch->forceFill($counts + ['imported_at' => now()])->save();

                return $batch->fresh();
            });
        } finally {
            fclose($handle);
        }
    }
    /**
     * Retry previously unmatched provider rows after webhook/usage ingestion catches up.
     * Already matched provider message IDs are never applied a second time.
     *
     * @return array{matched:int,discrepancy:int,duplicate:int,still_unmatched:int}
     */
    public function reconcilePending(): array
    {
        $result = ['matched' => 0, 'discrepancy' => 0, 'duplicate' => 0, 'still_unmatched' => 0];

        WhatsAppCostReconciliationItem::query()
            ->where('reconciliation_status', 'unmatched')
            ->whereNotNull('provider_message_id')
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$result): void {
                foreach ($items as $candidate) {
                    DB::transaction(function () use ($candidate, &$result): void {
                        $item = WhatsAppCostReconciliationItem::query()
                            ->whereKey($candidate->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $item || $item->reconciliation_status !== 'unmatched') {
                            return;
                        }

                        // Serialize retries for different unmatched rows that refer to the same
                        // provider message, so only one can apply its cost to the usage ledger.
                        $usage = WhatsAppMessageUsage::query()
                            ->where('provider_message_id', $item->provider_message_id)
                            ->lockForUpdate()
                            ->first();

                        if (! $usage) {
                            $result['still_unmatched']++;
                            return;
                        }

                        $alreadyMatched = WhatsAppCostReconciliationItem::query()
                            ->where('provider_message_id', $item->provider_message_id)
                            ->whereIn('reconciliation_status', ['matched', 'discrepancy'])
                            ->where('id', '!=', $item->id)
                            ->exists();

                        $batch = WhatsAppCostReconciliationBatch::query()
                            ->whereKey($item->batch_id)
                            ->lockForUpdate()
                            ->first();

                        if ($alreadyMatched) {
                            $item->forceFill([
                                'usage_id' => $usage->id,
                                'organization_id' => $usage->organization_id,
                                'reconciliation_status' => 'duplicate',
                                'issue' => 'Provider message ID was reconciled by another import.',
                            ])->save();

                            if ($batch) {
                                $batch->decrement('unmatched_count');
                                $batch->increment('duplicate_count');
                            }
                            $result['duplicate']++;
                            return;
                        }

                        $comparison = $this->compareUsageWithProvider(
                            $usage,
                            $item->provider_cost_amount,
                            $item->provider_cost_currency,
                            $item->pricing_category,
                            $item->billable
                        );
                        $newStatus = $comparison['details'] !== [] ? 'discrepancy' : 'matched';
                        $item->forceFill([
                            'usage_id' => $usage->id,
                            'organization_id' => $usage->organization_id,
                            'internal_cost_amount' => $usage->meta_cost_amount,
                            'internal_cost_currency' => $usage->meta_cost_currency,
                            'internal_pricing_category' => $usage->meta_pricing_category,
                            'internal_billable' => $usage->meta_billable,
                            'discrepancy_details' => $comparison['details'] ?: null,
                            'reconciliation_status' => $newStatus,
                            'issue' => $comparison['summary'],
                        ])->save();

                        $usage->forceFill([
                            'meta_cost_amount' => $item->provider_cost_amount,
                            'meta_cost_currency' => $item->provider_cost_currency,
                            'meta_pricing_category' => $item->pricing_category ?: $usage->meta_pricing_category,
                            'meta_billable' => $item->billable,
                        ])->save();

                        if ($batch) {
                            $batch->decrement('unmatched_count');
                            $batch->increment($newStatus . '_count');
                        }
                        $result[$newStatus === 'discrepancy' ? 'discrepancy' : 'matched']++;
                    });
                }
            });

        return $result;
    }


    /**
     * Compare pre-existing internal values only when the internal ledger already has a value.
     * Null internal cost/category/billable fields mean "not yet known", not a discrepancy.
     *
     * @return array{details:array<string,array{internal:mixed,provider:mixed}>,summary:?string}
     */
    /**
     * Normalize a provider amount without floating-point conversion. The database
     * column is DECIMAL(12, 6), so values must fit its six integer and six fraction
     * digits; excess fractional precision is rejected rather than silently rounded.
     */
    private function normalizeProviderAmount(string $amount): ?string
    {
        if (! preg_match('/^(?:\d+)(?:\.\d{1,6})?$/', $amount)) {
            return null;
        }

        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;

        if (strlen($integer) > 6) {
            return null;
        }

        return $integer . '.' . str_pad($fraction, 6, '0');
    }

    private function compareUsageWithProvider(
        WhatsAppMessageUsage $usage,
        ?string $providerAmount,
        ?string $providerCurrency,
        ?string $providerCategory,
        ?bool $providerBillable
    ): array {
        $details = [];

        if ($usage->meta_cost_amount !== null && $providerAmount !== null
            && $this->normalizeProviderAmount((string) $usage->meta_cost_amount) !== $providerAmount) {
            $details['cost_amount'] = ['internal' => (string) $usage->meta_cost_amount, 'provider' => $providerAmount];
        }
        if ($usage->meta_cost_currency !== null && $providerCurrency !== null
            && strtoupper((string) $usage->meta_cost_currency) !== strtoupper($providerCurrency)) {
            $details['currency'] = ['internal' => (string) $usage->meta_cost_currency, 'provider' => $providerCurrency];
        }
        if ($usage->meta_pricing_category !== null && $providerCategory !== null && $providerCategory !== ''
            && strtolower((string) $usage->meta_pricing_category) !== strtolower($providerCategory)) {
            $details['pricing_category'] = ['internal' => (string) $usage->meta_pricing_category, 'provider' => $providerCategory];
        }
        if ($usage->meta_billable !== null && $providerBillable !== null
            && (bool) $usage->meta_billable !== $providerBillable) {
            $details['billable'] = ['internal' => (bool) $usage->meta_billable, 'provider' => $providerBillable];
        }

        return [
            'details' => $details,
            'summary' => $details === [] ? null : 'Provider data differs from existing internal values: ' . implode(', ', array_keys($details)) . '.',
        ];
    }

}

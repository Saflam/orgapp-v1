<?php

namespace Modules\Core\Services;

use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Core\Models\PaymentReceipt;

class PaymentReceiptService
{
    public function issue(
        Organization $organization,
        string $ownerModule,
        string $sourceType,
        int $sourceId,
        string $receiptNumber,
        array $data = [],
        ?string $documentPath = null,
    ): PaymentReceipt {
        $existing = PaymentReceipt::query()
            ->where('organization_id', $organization->getKey())
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return PaymentReceipt::query()->create([
            'organization_id' => $organization->getKey(),
            'owner_module' => $ownerModule,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'receipt_number' => $receiptNumber !== ''
                ? $receiptNumber
                : 'RCP-' . Str::upper(Str::random(12)),
            'issued_at' => now(),
            'data' => $data,
            'document_path' => $documentPath,
        ]);
    }
}

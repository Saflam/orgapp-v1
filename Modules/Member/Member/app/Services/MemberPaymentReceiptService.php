<?php

namespace Modules\Member\Services;

use Modules\Core\Models\PaymentReceipt;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Core\Services\PaymentReceiptService;
use Modules\Member\Models\MemberPayment;

class MemberPaymentReceiptService
{
    public function __construct(
        private readonly OrganizationModuleService $modules,
        private readonly PaymentReceiptService $receipts,
    ) {}

    public function issueFallbackReceipt(MemberPayment $payment): ?PaymentReceipt
    {
        $organization = $payment->organization;

        if ($this->modules->isEnabled($organization, 'Finance')) {
            return null;
        }

        return $this->receipts->issue(
            organization: $organization,
            ownerModule: 'Member',
            sourceType: 'member_payment',
            sourceId: $payment->getKey(),
            receiptNumber: (string) $payment->receipt_number,
            data: [
                'payment_id' => $payment->id,
                'member_id' => $payment->member_id,
                'application_id' => $payment->membership_application_id,
                'membership_id' => $payment->membership_id,
                'amount' => (string) $payment->total_amount,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'received_at' => $payment->received_at?->toISOString(),
                'collector_scope_type' => $payment->collector_scope_type,
                'collector_scope_id' => $payment->collector_scope_id,
                'lines' => $payment->load('lines.obligation.paymentItem')->lines
                    ->map(fn ($line) => [
                        'obligation_id' => $line->member_payment_obligation_id,
                        'item_title' => $line->obligation?->item_title,
                        'amount' => (string) $line->amount,
                    ])
                    ->values()
                    ->all(),
            ],
        );
    }
}

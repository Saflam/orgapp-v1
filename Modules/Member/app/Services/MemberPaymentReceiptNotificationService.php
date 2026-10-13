<?php

namespace Modules\Member\Services;

use App\Models\User;
use Modules\Core\Models\PaymentReceipt;
use Modules\Core\Services\NotificationService;
use Modules\Member\Models\MemberPayment;

class MemberPaymentReceiptNotificationService
{
    public function __construct(
        private readonly MemberPaymentReceiptService $receipts,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Create the Member fallback receipt when Finance is inactive and create
     * its notification exactly once.
     *
     * Receipt creation and notification delivery remain separate concerns:
     * the receipt service owns the receipt artifact, while Core owns the
     * notification and delivery lifecycle.
     */
    public function send(MemberPayment $payment): ?array
    {
        $receipt = $this->receipts->issueFallbackReceipt($payment);

        if ($receipt === null) {
            return null;
        }

        $user = $payment->application?->user ?? $payment->member?->user;

        if ($user === null) {
            throw new \RuntimeException(
                'Unable to determine the receipt recipient for member payment ['
                . $payment->id
                . '].'
            );
        }

        return $this->notifications->notifyOnce(
            organization: $payment->organization,
            user: $user,
            event: 'member.payment.receipt.issued',
            title: 'Member payment receipt',
            body: "Your payment of {$payment->currency} {$payment->total_amount} has been received. Receipt {$receipt->receipt_number}.",
            dedupeKey: 'member-payment:' . $payment->id,
            data: [
                'payment_id' => $payment->id,
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
            ],
            sourceModule: 'Member',
        );
    }
}

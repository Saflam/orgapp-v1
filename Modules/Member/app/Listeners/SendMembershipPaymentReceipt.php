<?php

namespace Modules\Member\Listeners;

use Modules\Member\Events\MembershipPaymentReceived;
use Modules\Member\Services\MemberPaymentReceiptNotificationService;

class SendMembershipPaymentReceipt
{
    public function __construct(
        private readonly MemberPaymentReceiptNotificationService $receipts,
    ) {}

    public function handle(MembershipPaymentReceived $event): void
    {
        $this->receipts->send($event->payment);
    }
}

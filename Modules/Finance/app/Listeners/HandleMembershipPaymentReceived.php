<?php

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\MemberPaymentFinanceService;
use Modules\Member\Events\MembershipPaymentReceived;

class HandleMembershipPaymentReceived
{
    public function __construct(
        private readonly MemberPaymentFinanceService $payments,
    ) {}

    public function handle(MembershipPaymentReceived $event): void
    {
        $this->payments->handle($event->payment);
    }
}

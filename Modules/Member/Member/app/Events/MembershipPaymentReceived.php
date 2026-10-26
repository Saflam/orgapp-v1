<?php
namespace Modules\Member\Events;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Member\Models\MemberPayment;
class MembershipPaymentReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;
    public function __construct(public readonly MemberPayment $payment) {}
}

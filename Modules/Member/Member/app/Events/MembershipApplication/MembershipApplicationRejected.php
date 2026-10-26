<?php

namespace Modules\Member\Events\MembershipApplication;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Member\Models\MembershipApplication;

class MembershipApplicationRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly MembershipApplication $application) {}
}

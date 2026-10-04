<?php

namespace Modules\Core\Contracts;

use App\Models\User;
use Modules\Core\Models\OtpChallenge;

interface OtpChannel
{
    public function send(
        User $user,
        OtpChallenge $challenge,
        string $code,
    ): void;
}
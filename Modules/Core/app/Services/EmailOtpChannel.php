<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Contracts\OtpChannel;
use Modules\Core\Models\OtpChallenge;

class EmailOtpChannel implements OtpChannel
{
    public function send(
        User $user,
        OtpChallenge $challenge,
        string $code,
    ): void {
        Notification::route('mail', $user->email)
            ->notify(
                new \Modules\Core\Notifications\OtpNotification($code)
            );
    }
}
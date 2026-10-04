<?php

namespace Modules\Core\Services;

use App\Models\User;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Models\LoginActivity;

class LoginActivityService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function recordLogin(
        User $user,
        string $channel,
        ?PersonalAccessToken $token = null,
        ?Request $request = null,
    ): LoginActivity {
        $organizationId = null;

        if ($this->currentOrganization->check()) {
            $organizationId = $this->currentOrganization
                ->get()
                ->id;
        }

        return LoginActivity::create([
            'user_id' => $user->id,
            'organization_id' => $organizationId,
            'token_id' => $token?->id,
            'channel' => $channel,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'logged_in_at' => now(),
        ]);
    }

    public function recordLogout(
        LoginActivity $activity,
    ): void {
        $activity->update([
            'logged_out_at' => now(),
        ]);
    }
}
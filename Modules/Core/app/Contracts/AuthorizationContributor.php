<?php

namespace Modules\Core\Contracts;

use App\Models\User;
use Modules\Core\Data\AuthorizationContext;

interface AuthorizationContributor
{
    public function can(
        User $user,
        string $permissionCode,
        AuthorizationContext $context,
    ): bool;
}

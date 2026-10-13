<?php

namespace Modules\Core\Providers;

use Modules\Core\Contracts\PermissionProvider;

class CorePermissionProvider implements PermissionProvider
{
    public function permissions(): array
    {
        return [
            [
                'module' => 'Core',
                'name' => 'Manage notification channels',
                'code' => 'notification.channel.manage',
                'description' => 'Manage the organization notification channel configuration.',
            ],
        ];
    }
}

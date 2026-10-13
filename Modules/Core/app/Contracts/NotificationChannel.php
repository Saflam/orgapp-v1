<?php

namespace Modules\Core\Contracts;

use Modules\Core\Models\NotificationDelivery;

interface NotificationChannel
{
    public function name(): string;

    /** @param array<string, mixed> $credentials */
    public function send(NotificationDelivery $delivery, array $credentials): void;
}

<?php

namespace Modules\Core\Contracts;

interface AuthorizationContext
{
    public function contextType(): string;

    public function contextId(): int|string;

    public function organizationId(): int;
}
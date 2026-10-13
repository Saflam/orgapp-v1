<?php

namespace Modules\Core\Data;

use Modules\Core\Contracts\AuthorizationContext as AuthorizationContextContract;

final readonly class AuthorizationContext implements AuthorizationContextContract
{
    public function __construct(
        public int $organizationId,
        public ?int $unitId = null,
    ) {}

    public function contextType(): string
    {
        return $this->unitId === null ? 'organization' : 'unit';
    }

    public function contextId(): int|string
    {
        return $this->unitId ?? $this->organizationId;
    }

    public function organizationId(): int
    {
        return $this->organizationId;
    }
}
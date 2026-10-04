<?php

namespace Modules\Core\Data;

final readonly class AuthorizationContext
{
    public function __construct(
        public int $organizationId,
        public ?int $unitId = null,
    ) {}
}
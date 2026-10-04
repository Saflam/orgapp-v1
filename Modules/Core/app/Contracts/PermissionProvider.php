<?php

namespace Modules\Core\Contracts;

interface PermissionProvider
{
    /**
     * @return array<int, array{
     *     module: string,
     *     name: string,
     *     code: string,
     *     description?: string|null
     * }>
     */
    public function permissions(): array;
}